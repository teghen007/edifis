<?php

use App\Livewire\Field\AttendanceScanner;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
    config(['edifis.node_id' => 'node-test-field']);
});

it('opens a session and records a scan via Domain Action', function () {
    $teacher = User::create([
        'id' => tid('user.field.tch'),
        'name' => 'Field Teacher',
        'email' => 'field.tch@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $teacher->assignRole('subject_teacher');

    Livewire::actingAs($teacher)
        ->test(AttendanceScanner::class)
        ->set('classId', tid('class.f1'))
        ->set('subjectId', tid('subj.math'))
        ->call('openSession')
        ->assertSet('sessionOpen', true)
        ->assertSet('sessionId', fn ($id) => is_string($id));

    expect(AttendanceSession::count())->toBe(1);
});

it('duplicate scan in same session returns replay status', function () {
    $teacher = User::create([
        'id' => tid('user.field.dup'),
        'name' => 'Dup Teacher',
        'email' => 'field.dup@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $teacher->assignRole('subject_teacher');

    $component = Livewire::actingAs($teacher)
        ->test(AttendanceScanner::class)
        ->set('classId', tid('class.f1'))
        ->set('subjectId', tid('subj.math'))
        ->call('openSession');

    $component
        ->set('scanStudentId', tid('stu.alice'))
        ->call('scan')
        ->assertSet('lastScanStatus', 'scanned');

    $count = AttendanceEvent::count();

    $component
        ->set('scanStudentId', tid('stu.alice'))
        ->call('scan')
        ->assertSet('lastScanStatus', 'replay');

    expect(AttendanceEvent::count())->toBe($count);
});

it('manual override requires a reason', function () {
    $teacher = User::create([
        'id' => tid('user.field.ovr'),
        'name' => 'Override Teacher',
        'email' => 'field.ovr@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $teacher->assignRole('subject_teacher');

    $component = Livewire::actingAs($teacher)
        ->test(AttendanceScanner::class)
        ->set('classId', tid('class.f1'))
        ->set('subjectId', tid('subj.math'))
        ->call('openSession');

    // Override without reason → validation error
    $component
        ->set('scanSource', 'manual_override')
        ->set('scanStudentId', tid('stu.bob'))
        ->call('scan')
        ->assertHasErrors(['overrideReason']);
});
