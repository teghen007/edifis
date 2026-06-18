<?php

use App\Models\User;
use App\Domain\Audit\Models\AuditEntry;
use App\Domain\Students\Actions\EnrolStudent;
use App\Domain\Issuance\Actions\IssueItemsToStudent;
use App\Domain\Issuance\Models\CatalogueItem;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('secretary can enrol a student via Domain Action', function () {
    $secretary = User::create([
        'id' => tid('user.sec.web'),
        'name' => 'Secretary Web',
        'email' => 'sec.web@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $secretary->assignRole('secretary');

    $response = actingAs($secretary)->postJson('/api/students', [
        'student' => [
            'given_name' => 'Filament',
            'family_name' => 'Test',
            'current_class_id' => tid('class.web.1'),
        ],
        'consent' => [
            'consenter_name' => 'Parent Web',
            'relationship' => 'mother',
            'scope' => ['academic_records'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['id', 'master_pea_id', 'given_name', 'family_name']);
});

it('bursar can issue items via Domain Action (API → Filament delegates to same)', function () {
    CatalogueItem::create([
        'id' => tid('cat.web.1'),
        'name' => 'Web Test Book',
        'cost' => 5000,
        'category' => 'textbook',
    ]);

    $bursar = User::create([
        'id' => tid('user.bur.web'),
        'name' => 'Bursar Web',
        'email' => 'bur.web@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $bursar->assignRole('bursar');

    $response = actingAs($bursar)->postJson('/api/issuance/issue', [
        'batch_id' => tid('batch.web.1'),
        'student_id' => tid('stu.web.1'),
        'signature_ref' => 'sig-web-1',
        'items' => [['catalogue_item_id' => tid('cat.web.1')]],
    ]);

    $response->assertCreated();
});

it('VACUUM command writes audit and enforces principal-only gate', function () {
    $principal = User::create([
        'id' => tid('user.vac.web'),
        'name' => 'VAC Web',
        'email' => 'vac.web@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $principal->assignRole('principal');

    $target = User::create([
        'id' => tid('user.tgt.web'),
        'name' => 'Target Web',
        'email' => 'tgt.web@test.local',
        'password' => 'secret',
        'active' => true,
    ]);

    $auditBefore = AuditEntry::count();

    $response = actingAs($principal)->postJson('/api/vacuum/command', [
        'command' => 'deactivate_account',
        'target' => ['type' => 'account', 'account_id' => $target->id],
        'reason' => 'Web disciplinary action',
        'confirm' => true,
    ]);

    $response->assertOk();
    expect(AuditEntry::count())->toBeGreaterThan($auditBefore);

    $audit = AuditEntry::where('entity_id', $target->id)
        ->where('action', 'vacuum.deactivate_account')
        ->first();
    expect($audit)->not->toBeNull();
    expect($audit->actor_role)->toBe('principal');
});

it('teacher cannot access VACUUM command', function () {
    $teacher = User::create([
        'id' => tid('user.tch.web'),
        'name' => 'Teacher Web',
        'email' => 'tch.web@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $teacher->assignRole('subject_teacher');

    $response = actingAs($teacher)->postJson('/api/vacuum/command', [
        'command' => 'deactivate_account',
        'target' => ['type' => 'account', 'account_id' => tid('user.tgt.web')],
        'reason' => 'test',
        'confirm' => false,
    ]);

    $response->assertStatus(403);
});

it('teacher cannot access issuance (bursar-only)', function () {
    $teacher = User::create([
        'id' => tid('user.tch2.web'),
        'name' => 'Teacher 2 Web',
        'email' => 'tch2.web@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $teacher->assignRole('subject_teacher');

    $response = actingAs($teacher)->postJson('/api/issuance/issue', [
        'batch_id' => tid('batch.web.2'),
        'student_id' => tid('stu.web.2'),
        'signature_ref' => 'sig-web-2',
        'items' => [['catalogue_item_id' => tid('cat.web.1')]],
    ]);

    $response->assertStatus(403);
});

it('parent can view child balance via API', function () {
    \App\Domain\Ledger\Models\LedgerEntry::create([
        'id' => tid('ledger.web.1'),
        'student_id' => tid('stu.bal.web'),
        'source_event_id' => tid('evt.web.1'),
        'amount' => 5000,
        'posted_at' => now(),
    ]);

    $parent = User::create([
        'id' => tid('user.par.web'),
        'name' => 'Parent Web',
        'email' => 'par.web@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $parent->assignRole('parent');

    $response = actingAs($parent)->getJson('/api/fees/students/' . tid('stu.bal.web') . '/balance');

    $response->assertOk()
        ->assertJson(['balance' => 5000]);
});

it('Filament resource canAccess delegates to Spatie roles', function () {
    // Test that the static canAccess methods on Resources check Spatie roles
    $bursar = User::create([
        'id' => tid('user.br.can'),
        'name' => 'Bursar Can',
        'email' => 'br.can@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $bursar->assignRole('bursar');

    actingAs($bursar);
    expect(\App\Filament\Resources\IssuanceResource::canAccess())->toBeTrue();
    expect(\App\Filament\Resources\FeeResource::canAccess())->toBeTrue();
    expect(\App\Filament\Resources\StudentResource::canAccess())->toBeTrue();

    $teacher = User::create([
        'id' => tid('user.tch.can'),
        'name' => 'Teacher Can',
        'email' => 'tch.can@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $teacher->assignRole('subject_teacher');

    actingAs($teacher);
    expect(\App\Filament\Resources\IssuanceResource::canAccess())->toBeFalse();
    expect(\App\Filament\Resources\FeeResource::canAccess())->toBeFalse();
    expect(\App\Filament\Resources\StudentResource::canAccess())->toBeFalse();

    expect(\App\Filament\Pages\Vacuum::canAccess())->toBeFalse();
});
