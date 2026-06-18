<?php

use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Actions\RecordScan;
use App\Domain\Attendance\Actions\VoidScan;
use App\Models\User;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('duplicate scan of same student in session is idempotent', function () {
    $session = AttendanceSession::create([
        'id' => tid('att.session1'),
        'class_id' => tid('class.f1'),
        'subject_id' => tid('subj.math'),
        'period' => 'AM',
        'status' => 'open',
        'opened_at' => now(),
    ]);

    $scan = app(RecordScan::class);

    $first = $scan->handle(
        sessionId: $session->id,
        studentId: tid('stu.goodness'),
        source: 'qr_scan',
    );
    expect($first)->not->toBeNull();

    $countBefore = AttendanceEvent::where('session_id', $session->id)->count();

    $replay = $scan->handle(
        sessionId: $session->id,
        studentId: tid('stu.goodness'),
        source: 'qr_scan',
    );

    expect($replay['status'] ?? null)->toBe('replay');
    expect(AttendanceEvent::where('session_id', $session->id)->count())->toBe($countBefore);
});

it('override requires a reason', function () {
    $session = AttendanceSession::create([
        'id' => tid('att.session2'),
        'class_id' => tid('class.f1'),
        'subject_id' => tid('subj.math'),
        'period' => 'AM',
        'status' => 'open',
        'opened_at' => now(),
    ]);

    $scan = app(RecordScan::class);

    expect(fn () => $scan->handle(
        sessionId: $session->id,
        studentId: tid('stu.john'),
        source: 'manual_override',
    ))->toThrow(\InvalidArgumentException::class);
});

it('tally reflects voids', function () {
    $session = AttendanceSession::create([
        'id' => tid('att.session3'),
        'class_id' => tid('class.f1'),
        'subject_id' => tid('subj.math'),
        'period' => 'AM',
        'status' => 'open',
        'opened_at' => now(),
    ]);

    $scan = app(RecordScan::class);

    $scan->handle($session->id, tid('stu.alice'), 'qr_scan');
    $scan->handle($session->id, tid('stu.bob'), 'qr_scan');

    $present = AttendanceEvent::where('session_id', $session->id)
        ->where('status', 'present')->count();
    expect($present)->toBe(2);

    $voidCountBefore = AttendanceEvent::where('session_id', $session->id)
        ->where('status', 'void')->count();
    expect($voidCountBefore)->toBe(0);

    app(VoidScan::class)->handle(
        eventId: AttendanceEvent::where('session_id', $session->id)
            ->where('student_id', tid('stu.alice'))
            ->first()->id,
        reason: 'Left early',
        actorId: tid('user.teacher'),
    );

    $voidCountAfter = AttendanceEvent::where('session_id', $session->id)
        ->where('status', 'void')->count();
    expect($voidCountAfter)->toBe(1);

    $present = AttendanceEvent::where('session_id', $session->id)
        ->where('status', 'present')->count();
    expect($present)->toBe(2);
});

it('attendance sessions API flows correctly', function () {
    $teacher = User::create([
        'id' => tid('user.teacher.att'),
        'name' => 'Teacher Test',
        'email' => 'teacher.att@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $teacher->assignRole('subject_teacher');

    $open = actingAs($teacher)->postJson('/api/attendance/sessions', [
        'class_id' => tid('class.f1'),
        'subject_id' => tid('subj.math'),
        'period' => 'AM',
    ]);

    $open->assertCreated();
    $sessionId = $open->json('id');

    actingAs($teacher)->postJson("/api/attendance/sessions/{$sessionId}/scan", [
        'student_id' => tid('stu.goodness'),
        'source' => 'qr_scan',
    ])->assertCreated();

    $tally = actingAs($teacher)->getJson("/api/attendance/sessions/{$sessionId}/tally");
    $tally->assertOk()->assertJson(['scanned' => 1]);
});
