<?php

use App\Domain\Academics\Models\Mark;
use App\Domain\Sync\Services\ConflictResolver;
use function Pest\Laravel\postJson;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('a linear offline edit is accepted', function () {
    $conflict = app(ConflictResolver::class);

    $base = [
        'id' =>'0b7fa264-2623-4619-96c7-c00775a82d6b',
        'revision' => 'r1',
        'revision_parent' => null,
        'student_id' =>'6e65ca4b-9043-464b-811b-0e38b9568e12',
        'subject_id' =>'1949ea34-8217-4560-8512-11fcf2f7f76f',
        'class_id' =>'d6d581a8-8f0a-49a2-ba94-475d3ccc9584',
        'sequence' => 'T1-Seq1',
        'owner_teacher_id' =>'499308c2-c048-4e7c-8809-9054fc4cf3ff',
        'score' => 12.0,
        'max_score' => 20.0,
        'recorded_at' => now()->toIso8601ZuluString(),
    ];

    $conflict->resolve('mark', $base, 'r1');

    // Linear edit from the cloud (revision_parent matches)
    $edit = array_merge($base, [
        'revision' => 'r2',
        'revision_parent' => 'r1',
        'score' => 15.0,
    ]);

    $result = $conflict->resolve('mark', $edit, 'r2');

    expect($result['status'])->toBe('applied');

    $saved = Mark::find($base['id']);
    expect($saved->score)->toBe(15.0);
});

it('a true divergent conflict yields cloud-wins', function () {
    $conflict = app(ConflictResolver::class);

    $base = [
        'id' =>'3ed7cd95-382c-4945-9e69-edc4b0c19bf2',
        'revision' => 'r1',
        'revision_parent' => null,
        'student_id' =>'5327f546-962f-4b91-b700-563612ce96a0',
        'subject_id' =>'6a5c6ecd-63e8-4e83-8f1a-94fa7002f27f',
        'class_id' =>'715c187f-213b-44fd-b8ed-229bfbf0db1d',
        'sequence' => 'T1-Seq1',
        'owner_teacher_id' =>'4a60f9aa-c21e-4bba-ab79-1c85057c1c54',
        'score' => 10.0,
        'max_score' => 20.0,
        'recorded_at' => now()->toIso8601ZuluString(),
    ];

    $conflict->resolve('mark', $base, 'r1');

    // A node edits online (revision_parent = r1)
    $nodeEdit = array_merge($base, [
        'revision' => 'r1-node',
        'revision_parent' => 'r1',
        'score' => 18.0,
    ]);

    $cloudEdit = array_merge($base, [
        'revision' => 'r2',
        'revision_parent' => 'r1',
        'score' => 16.0,
    ]);

    // Cloud edit applies first (linear)
    $conflict->resolve('mark', $cloudEdit, 'r2');

    // Node edit has same parent but different revision ? true conflict
    $result = $conflict->resolve('mark', $nodeEdit, 'r1-node');

    expect($result['status'])->toBe('conflict');
    expect($result['resolution'])->toBe('cloud_wins');
    expect($result['rejected_revision'])->toBe('r1-node');

    // Cloud version preserved
    $saved = Mark::find($base['id']);
    expect($saved->score)->toBe(16.0);
});
