<?php

use App\Domain\Promotion\Models\PromotionRuleset;
use App\Domain\Promotion\Actions\ComputePromotion;
use App\Domain\Promotion\Actions\OverridePromotion;
use App\Domain\Academics\Models\Mark;

beforeEach(function () {
    config(['edifis.mode' => 'local']);

    PromotionRuleset::create([
        'version' => '2026-v1',
        'baseline' => 10.0,
        'coefficients' => [],
    ]);
});

it('coefficient weighting matches a hand-computed sample', function () {
    PromotionRuleset::where('version', '2026-v1')->update([
        'coefficients' => [
            tid('subj.math') => 2.0,
            tid('subj.english') => 1.0,
        ],
    ]);

    Mark::create([
        'id' => tid('mark.promo.math'),
        'revision' => 'r1',
        'student_id' => tid('stu.promo.1'),
        'subject_id' => tid('subj.math'),
        'class_id' => tid('class.f1'),
        'sequence' => '2026-T1-Seq1',
        'owner_teacher_id' => tid('user.teacher'),
        'score' => 15.0,
        'max_score' => 20.0,
        'recorded_at' => now(),
    ]);

    Mark::create([
        'id' => tid('mark.promo.english'),
        'revision' => 'r1',
        'student_id' => tid('stu.promo.1'),
        'subject_id' => tid('subj.english'),
        'class_id' => tid('class.f1'),
        'sequence' => '2026-T1-Seq1',
        'owner_teacher_id' => tid('user.teacher'),
        'score' => 18.0,
        'max_score' => 20.0,
        'recorded_at' => now(),
    ]);

    $compute = app(ComputePromotion::class);
    $decision = $compute->handle(tid('stu.promo.1'), '2026', ['2026-T1-Seq1'], 'general');

    expect($decision->yearly_average)->toBe(16.0);
    expect($decision->outcome)->toBe('advance');
});

it('baseline boundary exactly 10 advances', function () {
    PromotionRuleset::where('version', '2026-v1')->update(['baseline' => 10.0]);

    Mark::create([
        'id' => tid('mark.promo.2'),
        'revision' => 'r1',
        'student_id' => tid('stu.promo.2'),
        'subject_id' => tid('subj.math'),
        'class_id' => tid('class.f1'),
        'sequence' => '2026-T1-Seq1',
        'owner_teacher_id' => tid('user.teacher'),
        'score' => 10.0,
        'max_score' => 20.0,
        'recorded_at' => now(),
    ]);

    $compute = app(ComputePromotion::class);
    $decision = $compute->handle(tid('stu.promo.2'), '2026', ['2026-T1-Seq1'], 'general');

    expect($decision->outcome)->toBe('advance');
    expect($decision->ruleset_version)->toBe('2026-v1');
});

it('an override is audited and does not edit the original decision', function () {
    Mark::create([
        'id' => tid('mark.promo.3'),
        'revision' => 'r1',
        'student_id' => tid('stu.promo.3'),
        'subject_id' => tid('subj.math'),
        'class_id' => tid('class.f1'),
        'sequence' => '2026-T1-Seq1',
        'owner_teacher_id' => tid('user.teacher'),
        'score' => 5.0,
        'max_score' => 20.0,
        'recorded_at' => now(),
    ]);

    $compute = app(ComputePromotion::class);
    $decision = $compute->handle(tid('stu.promo.3'), '2026', ['2026-T1-Seq1'], 'general');

    expect($decision->outcome)->toBe('repeat');

    $auditBefore = \App\Domain\Audit\Models\AuditEntry::count();

    $override = app(OverridePromotion::class);
    $override->handle(
        decisionId: $decision->id,
        newOutcome: 'advance',
        reason: 'Medical exemption approved by PEA',
        principalId: tid('user.principal'),
    );

    $decision->refresh();
    expect($decision->outcome)->toBe('repeat');

    $auditAfter = \App\Domain\Audit\Models\AuditEntry::count();
    expect($auditAfter)->toBeGreaterThan($auditBefore);

    $lastAudit = \App\Domain\Audit\Models\AuditEntry::latest()->first();
    expect($lastAudit->action)->toBe('promotion.override');
    expect($lastAudit->entity_type)->toBe('promotion_decision');
});
