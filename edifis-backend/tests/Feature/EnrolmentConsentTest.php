<?php

use App\Models\User;
use function Pest\Laravel\postJson;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('enrols a student with valid consent', function () {
    $user = User::create([
        'id' =>'87424f36-f700-4b0a-8faf-401bab24f46b',
        'name' => 'Secretary One',
        'email' => 'secretary@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $user->assignRole('secretary');

    $response = actingAs($user)->postJson('/api/students', [
        'student' => [
            'given_name' => 'Goodness',
            'family_name' => 'Shei',
            'sex' => 'F',
            'date_of_birth' => '2008-04-15',
            'current_class_id' =>'7ad7fb04-20bc-4b1e-a3ac-bcb8399ab53c',
        ],
        'consent' => [
            'consenter_name' => 'Martha Shei',
            'relationship' => 'mother',
            'consenter_contact' => '+237670000001',
            'scope' => ['academic_records', 'photo_on_id', 'parent_portal'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['id', 'master_pea_id', 'given_name', 'family_name', 'current_class_id', 'enrolled_at']);
});

it('creates a new consent version on scope change', function () {
    $user = User::create([
        'id' =>'f69e03c2-8d66-432a-b227-bbfcb5e9fb18',
        'name' => 'Secretary Two',
        'email' => 'secretary2@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $user->assignRole('secretary');

    $response = actingAs($user)->postJson('/api/students', [
        'student' => [
            'given_name' => 'John',
            'family_name' => 'Doe',
            'current_class_id' =>'adb1785e-6c2f-47b3-a23e-6bedec9ee561',
        ],
        'consent' => [
            'consenter_name' => 'Jane Doe',
            'relationship' => 'mother',
            'scope' => ['academic_records'],
        ],
    ]);

    $response->assertCreated();

    $consents = \App\Domain\Consent\Models\Consent::where(
        'student_id',
        $response->json('id')
    )->get();

    expect($consents)->toHaveCount(1);
    expect($consents->first()->version)->toBe(1);
});
