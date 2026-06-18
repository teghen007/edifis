<?php

use App\Domain\Onboarding\Models\SchoolRequest;
use App\Domain\Tenancy\Models\EdifisTenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\OnboardingApproved;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['edifis.mode' => 'cloud']);
});

it('submits a school onboarding request', function () {
    $response = postJson('/api/onboarding/request', [
        'school_name' => 'PSS Mankon',
        'school_code' => 'pssmankon',
        'location' => 'Bamenda',
        'contact_name' => 'Dr Mankon Principal',
        'contact_email' => 'principal@mankon.edu.cm',
        'contact_phone' => '+237677000001',
        'estimated_students' => 800,
    ]);

    $response->assertCreated()
        ->assertJson(['status' => 'submitted']);

    expect(SchoolRequest::where('school_code', 'pssmankon')->exists())->toBeTrue();
});

it('duplicate pending request is idempotent', function () {
    SchoolRequest::create([
        'id' => tid('req.dup.1'),
        'school_name' => 'Duplicate School',
        'school_code' => 'dupschool',
        'contact_name' => 'Dup Contact',
        'contact_email' => 'dup@test.local',
        'status' => 'pending',
    ]);

    $response = postJson('/api/onboarding/request', [
        'school_name' => 'Duplicate School',
        'school_code' => 'dupschool',
        'contact_name' => 'Dup Contact',
        'contact_email' => 'dup@test.local',
    ]);

    $response->assertOk()
        ->assertJson(['status' => 'already_submitted']);

    expect(SchoolRequest::where('school_code', 'dupschool')->count())->toBe(1);
});

it('approving a request onboards the school and emails claim code', function () {
    Mail::fake();

    $admin = User::create([
        'id' => tid('admin.pea.1'),
        'name' => 'PEA Admin',
        'email' => 'pea@myedifis.com',
        'password' => 'secret',
        'active' => true,
    ]);
    $admin->assignRole('pea_admin');

    $req = SchoolRequest::create([
        'id' => tid('req.approve.1'),
        'school_name' => 'Approval Test School',
        'school_code' => 'approvaltest',
        'contact_name' => 'Principal Test',
        'contact_email' => 'pt@approvaltest.myedifis.com',
        'status' => 'pending',
    ]);

    $response = actingAs($admin)->postJson("/api/onboarding/requests/{$req->id}/approve");

    $response->assertOk()
        ->assertJson(['status' => 'approved', 'school_code' => 'approvaltest']);

    // Tenant created
    expect(EdifisTenant::where('id', 'approvaltest')->exists())->toBeTrue();

    // Domain created
    expect(\Stancl\Tenancy\Database\Models\Domain::where('domain', 'approvaltest.myedifis.com')->exists())->toBeTrue();

    // Request updated
    $req->refresh();
    expect($req->status)->toBe('approved');
    expect($req->claim_code)->not->toBeNull();

    // Email sent
    Mail::assertSent(OnboardingApproved::class, function ($mail) {
        return $mail->hasTo('pt@approvaltest.myedifis.com');
    });

    // Audit entry carries the real actor role (not hardcoded string)
    $audit = \App\Domain\Audit\Models\AuditEntry::where('entity_id', $req->id)
        ->where('action', 'school_request.approve')
        ->first();
    expect($audit)->not->toBeNull();
    expect($audit->actor_role)->toBe('pea_admin');
});

it('non-pea_admin gets 403 on approve', function () {
    $teacher = User::create([
        'id' => tid('teacher.reject.1'),
        'name' => 'Unauthorised Teacher',
        'email' => 'teacher.reject@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $teacher->assignRole('subject_teacher');

    $req = SchoolRequest::create([
        'id' => tid('req.reject.1'),
        'school_name' => 'Unauthorised Attempt',
        'school_code' => 'unauthschool',
        'contact_name' => 'Bad Actor',
        'contact_email' => 'bad@test.local',
        'status' => 'pending',
    ]);

    $response = actingAs($teacher)->postJson("/api/onboarding/requests/{$req->id}/approve");
    $response->assertStatus(403);
});

it('non-pea_admin gets 403 on list', function () {
    $teacher = User::create([
        'id' => tid('teacher.reject.2'),
        'name' => 'List Reject',
        'email' => 'list.reject@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $teacher->assignRole('subject_teacher');

    $response = actingAs($teacher)->getJson('/api/onboarding/requests');
    $response->assertStatus(403);
});
