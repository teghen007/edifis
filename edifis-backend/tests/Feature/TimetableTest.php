<?php

use App\Domain\Timetable\Models\TimetableEntry;
use App\Models\User;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('VP can author a timetable entry', function () {
    $vp = User::create([
        'id' => '018f3c2a-6000-7000-a1b2-c3d4e5f60001',
        'name' => 'VP Test',
        'email' => 'vp@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $vp->assignRole('vice_principal');

    $response = actingAs($vp)->postJson('/api/timetable', [
        'class_id' =>'c0525a48-0b21-45d8-a556-ade587bd2e79',
        'subject_id' =>'a974629e-9b77-4b14-834c-f14a02f84d30',
        'teacher_id' =>'b72902d8-6513-421a-9ac6-2a7435cea183',
        'day_of_week' => 'Monday',
        'period_start' => '08:00',
        'period_end' => '09:00',
        'room' => 'Room 101',
    ]);

    $response->assertCreated()
        ->assertJson(['is_approved' => false]);
});

it('an unapproved timetable entry is not live', function () {
    $entry = TimetableEntry::create([
        'id' => '018f3c2a-3000-7000-a1b2-c3d4e5f60002',
        'class_id' =>'5f34b6bf-2db5-413a-93de-3f741044b848',
        'subject_id' =>'4815799d-1a1e-4bd3-b9c7-cf589ff325ff',
        'teacher_id' =>'73e3bae1-7052-4bb1-a740-f5777f13cf63',
        'day_of_week' => 'Monday',
        'period_start' => '09:00',
        'period_end' => '10:00',
        'created_by' => '018f3c2a-6000-7000-a1b2-c3d4e5f60001',
        'is_approved' => false,
    ]);

    expect($entry->is_approved)->toBeFalse();
    expect($entry->approved_at)->toBeNull();
});

it('principal approves timetable and it becomes live', function () {
    $principal = User::create([
        'id' => '018f3c2a-6000-7000-a1b2-c3d4e5f60001',
        'name' => 'Principal Test',
        'email' => 'principal@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $principal->assignRole('principal');

    $entry = TimetableEntry::create([
        'id' => '018f3c2a-3000-7000-a1b2-c3d4e5f60002',
        'class_id' =>'0faa2cbc-7805-4440-aa6e-4cfdb97c86fd',
        'subject_id' =>'6e3be4d2-9cb6-4fcc-a076-2c2bda2d58d5',
        'teacher_id' =>'ee9aa2fd-47ba-41b2-a441-c50de3b130dd',
        'day_of_week' => 'Tuesday',
        'period_start' => '08:00',
        'period_end' => '09:00',
        'created_by' => '018f3c2a-6000-7000-a1b2-c3d4e5f60001',
        'is_approved' => false,
    ]);

    $response = actingAs($principal)
        ->postJson("/api/timetable/{$entry->id}/approve");

    $response->assertOk()
        ->assertJson(['is_approved' => true, 'approved_by' => $principal->id]);

    $entry->refresh();
    expect($entry->is_approved)->toBeTrue();
    expect($entry->approved_by)->toBe($principal->id);
    expect($entry->approved_at)->not->toBeNull();
});

it('a teacher cannot author the timetable', function () {
    $teacher = User::create([
        'id' => '018f3c2a-6000-7000-a1b2-c3d4e5f60001',
        'name' => 'Teacher Test',
        'email' => 'teacher.tt@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $teacher->assignRole('subject_teacher');

    $response = actingAs($teacher)->postJson('/api/timetable', [
        'class_id' =>'cab2a51d-323d-4695-9332-f8e566f12054',
        'subject_id' =>'13243c50-42c3-4569-abf3-f46a738e1ffc',
        'teacher_id' => $teacher->id,
        'day_of_week' => 'Monday',
        'period_start' => '10:00',
        'period_end' => '11:00',
    ]);

    $response->assertStatus(403);
});
