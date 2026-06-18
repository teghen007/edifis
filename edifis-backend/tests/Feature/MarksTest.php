<?php

use App\Domain\Academics\Models\Mark;
use App\Domain\Academics\Actions\RecordMark;
use App\Domain\Sync\Services\ConflictResolver;
use App\Models\User;
use function Pest\Laravel\postJson;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('replay of an already-applied mark returns replay not conflict', function () {
    $conflict = app(ConflictResolver::class);

    $base = ['id' => '019ed500-0001-7000-a1b2-c3d4e5f64001', 'revision' => 'r1', 'revision_parent' => null, 'student_id' => '019ed500-0002-7000-a1b2-c3d4e5f60001', 'subject_id' => '019ed500-0003-7000-a1b2-c3d4e5f60001', 'class_id' => '019ed500-0004-7000-a1b2-c3d4e5f60001', 'sequence' => 'T1-Seq1', 'owner_teacher_id' => '019ed500-0005-7000-a1b2-c3d4e5f60003', 'score' => 12.0, 'max_score' => 20.0, 'recorded_at' => now()->toIso8601ZuluString()];

    $conflict->resolve('mark', $base, 'r1');

    $edit = array_merge($base, ['revision' => 'r2', 'revision_parent' => 'r1', 'score' => 15.0]);
    $first = $conflict->resolve('mark', $edit, 'r2');
    expect($first['status'])->toBe('applied');

    $replay = $conflict->resolve('mark', $edit, 'r2');
    expect($replay['status'])->toBe('replay');
});

it('linear edit writes an audit entry', function () {
    $conflict = app(ConflictResolver::class);

    $base = ['id' => '019ed500-0006-7000-a1b2-c3d4e5f64002', 'revision' => 'r1', 'revision_parent' => null, 'student_id' => '019ed500-0007-7000-a1b2-c3d4e5f60002', 'subject_id' => '019ed500-0008-7000-a1b2-c3d4e5f60001', 'class_id' => '019ed500-0009-7000-a1b2-c3d4e5f60001', 'sequence' => 'T1-Seq1', 'owner_teacher_id' => '019ed500-000a-7000-a1b2-c3d4e5f60003', 'score' => 10.0, 'max_score' => 20.0, 'recorded_at' => now()->toIso8601ZuluString()];

    $conflict->resolve('mark', $base, 'r1');

    $auditBefore = \App\Domain\Audit\Models\AuditEntry::count();

    $edit = array_merge($base, ['revision' => 'r2', 'revision_parent' => 'r1', 'score' => 14.0]);
    $conflict->resolve('mark', $edit, 'r2');

    expect(\App\Domain\Audit\Models\AuditEntry::count())->toBeGreaterThan($auditBefore);

    $editAudit = \App\Domain\Audit\Models\AuditEntry::where('action', 'mark.edit')
        ->where('entity_id', '019ed500-0006-7000-a1b2-c3d4e5f64002')
        ->first();

    expect($editAudit)->not->toBeNull();
    expect($editAudit->entity_type)->toBe('mark');
    expect((float) $editAudit->after['score'])->toBe(14.0);
});

it('divergent conflict writes mark.conflict audit and persists', function () {
    $conflict = app(ConflictResolver::class);

    $base = ['id' => '019ed500-000b-7000-a1b2-c3d4e5f64003', 'revision' => 'r1', 'revision_parent' => null, 'student_id' => '019ed500-000c-7000-a1b2-c3d4e5f60003', 'subject_id' => '019ed500-000d-7000-a1b2-c3d4e5f60001', 'class_id' => '019ed500-000e-7000-a1b2-c3d4e5f60001', 'sequence' => 'T1-Seq1', 'owner_teacher_id' => '019ed500-000f-7000-a1b2-c3d4e5f60003', 'score' => 10.0, 'max_score' => 20.0, 'recorded_at' => now()->toIso8601ZuluString()];

    $conflict->resolve('mark', $base, 'r1');

    $cloudEdit = array_merge($base, ['revision' => 'r2', 'revision_parent' => 'r1', 'score' => 16.0]);
    $conflict->resolve('mark', $cloudEdit, 'r2');

    $nodeEdit = array_merge($base, ['revision' => 'r1-node', 'revision_parent' => 'r1', 'score' => 18.0]);
    $result = $conflict->resolve('mark', $nodeEdit, 'r1-node');

    expect($result['status'])->toBe('conflict');
    expect($result['resolution'])->toBe('cloud_wins');
    expect($result['rejected_revision'])->toBe('r1-node');

    $conflictRow = DB::table('mark_conflicts')->where('mark_id', $base['id'])->first();
    expect($conflictRow)->not->toBeNull();
    expect($conflictRow->rejected_revision)->toBe('r1-node');
    expect($conflictRow->winning_revision)->toBe('r2');

    $audit = \App\Domain\Audit\Models\AuditEntry::where('action', 'mark.conflict')->where('entity_id', $base['id'])->first();
    expect($audit)->not->toBeNull();
});

it('rejected revision is never silently dropped', function () {
    $conflict = app(ConflictResolver::class);

    $base = ['id' => '019ed500-0010-7000-a1b2-c3d4e5f64004', 'revision' => 'r1', 'revision_parent' => null, 'student_id' => '019ed500-0011-7000-a1b2-c3d4e5f60004', 'subject_id' => '019ed500-0012-7000-a1b2-c3d4e5f60001', 'class_id' => '019ed500-0013-7000-a1b2-c3d4e5f60001', 'sequence' => 'T1-Seq1', 'owner_teacher_id' => '019ed500-0014-7000-a1b2-c3d4e5f60003', 'score' => 10.0, 'max_score' => 20.0, 'recorded_at' => now()->toIso8601ZuluString()];

    $conflict->resolve('mark', $base, 'r1');

    $cloudEdit = array_merge($base, ['revision' => 'r2', 'revision_parent' => 'r1', 'score' => 16.0]);
    $conflict->resolve('mark', $cloudEdit, 'r2');

    $rejectedRevision = 'r1-node';
    $nodeEdit = array_merge($base, ['revision' => $rejectedRevision, 'revision_parent' => 'r1', 'score' => 18.0]);
    $result = $conflict->resolve('mark', $nodeEdit, $rejectedRevision);

    expect($result['rejected_revision'])->toBe($rejectedRevision);

    $conflictRow = DB::table('mark_conflicts')->first();
    expect($conflictRow->rejected_revision)->toBe($rejectedRevision);

    $audit = \App\Domain\Audit\Models\AuditEntry::where('action', 'mark.conflict')->first();
    expect($audit)->not->toBeNull();
    expect($audit->before['revision'])->toBe($rejectedRevision);
});

it('a teacher records a mark via the API', function () {
    $teacher = User::create(['id' => '019ed500-0015-7000-a1b2-c3d4e5f64001', 'name' => 'Math Teacher', 'email' => 'math@test.local', 'password' => 'secret', 'active' => true]);
    $teacher->assignRole('subject_teacher');

    $response = actingAs($teacher)->postJson('/api/academics/marks', ['id' => '019ed500-0016-7000-a1b2-c3d4e5f64005', 'revision' => 'r1', 'student_id' => '019ed500-0017-7000-a1b2-c3d4e5f60001', 'subject_id' => '019ed500-0018-7000-a1b2-c3d4e5f60001', 'class_id' => '019ed500-0019-7000-a1b2-c3d4e5f60001', 'sequence' => 'T1-Seq1', 'owner_teacher_id' => '019ed500-001a-7000-a1b2-c3d4e5f60003', 'score' => 15.5, 'max_score' => 20]);

    $response->assertCreated()->assertJson(['score' => 15.5, 'max_score' => 20, 'published' => false]);

    $mark = Mark::find('019ed500-0016-7000-a1b2-c3d4e5f64005');
    expect($mark->revision)->toBe('r1');
    expect($mark->owner_teacher_id)->toBe($teacher->id);
});
