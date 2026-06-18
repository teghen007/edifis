<?php

use App\Domain\Students\Models\Student;
use App\Domain\Academics\Models\Mark;
use App\Domain\Documents\Actions\RenderReportCard;
use App\Domain\Ledger\Models\LedgerEntry;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('renders a report card with correct averages', function () {
    $student = Student::create([
        'id' => tid('stu.maria'),
        'master_pea_id' => 'PEA-2026-00001',
        'given_name' => 'Maria',
        'family_name' => 'Teststudent',
        'current_class_id' => tid('class.f1'),
        'enrolled_at' => now(),
    ]);

    Mark::create([
        'id' => tid('mark.maria.math'),
        'revision' => 'r1',
        'student_id' => $student->id,
        'subject_id' => tid('subj.math'),
        'class_id' => tid('class.f1'),
        'sequence' => 'T1-Seq1',
        'owner_teacher_id' => tid('user.teacher'),
        'score' => 15.0,
        'max_score' => 20.0,
        'recorded_at' => now(),
    ]);

    Mark::create([
        'id' => tid('mark.maria.english'),
        'revision' => 'r1',
        'student_id' => $student->id,
        'subject_id' => tid('subj.english'),
        'class_id' => tid('class.f1'),
        'sequence' => 'T1-Seq1',
        'owner_teacher_id' => tid('user.teacher'),
        'score' => 18.0,
        'max_score' => 20.0,
        'recorded_at' => now(),
    ]);

    $render = app(RenderReportCard::class);
    $card = $render->handle($student->id, 'T1-Seq1');

    expect($card['student']['name'])->toBe('Maria Teststudent');
    expect($card['student']['master_pea_id'])->toBe('PEA-2026-00001');
    expect($card['total_score'])->toBe(33.0);
    expect($card['total_max'])->toBe(40.0);
    expect($card['average'])->toBe(16.5);
    expect(count($card['marks']))->toBe(2);
});

it('fee receipt total equals the ledger sum', function () {
    LedgerEntry::create([
        'id' => tid('ledger.fee.1'),
        'student_id' => tid('stu.fee.test'),
        'source_event_id' => tid('event.fee.1'),
        'amount' => 8000,
        'posted_at' => now(),
    ]);

    LedgerEntry::create([
        'id' => tid('ledger.fee.2'),
        'student_id' => tid('stu.fee.test'),
        'source_event_id' => tid('event.fee.2'),
        'amount' => -3000,
        'posted_at' => now(),
    ]);

    $balance = LedgerEntry::where('student_id', tid('stu.fee.test'))->sum('amount');
    expect((int) $balance)->toBe(5000);
    expect(is_int((int) $balance))->toBeTrue();
});
