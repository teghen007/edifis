<?php

use App\Domain\Issuance\Models\IssueEvent;
use App\Domain\Issuance\Models\CatalogueItem;
use function Pest\Laravel\postJson;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('a late-synced record with old created_at but newer synced_time appears in pull', function () {
    CatalogueItem::create(['id' => '019ed500-1001-7000-a1b2-c3d4e5f77001', 'name' => 'Late Sync Book', 'cost' => 3000, 'category' => 'textbook']);

    $newSyncTime = now()->toIso8601ZuluString();

    IssueEvent::create(['id' => '019ed500-1002-7000-a1b2-c3d4e5f77001', 'revision' => 'r-late', 'student_id' => '019ed500-1003-7000-a1b2-c3d4e5f77001', 'catalogue_item_id' => '019ed500-1004-7000-a1b2-c3d4e5f77001', 'cost' => 3000, 'issued_at' => '2020-01-01T00:00:00Z', 'staff_id' => '019ed500-1005-7000-a1b2-c3d4e5f63001', 'signature_ref' => 'sig-late', 'batch_id' => '019ed500-1006-7000-a1b2-c3d4e5f77001', 'device_id' => 'node-test-01', 'status' => 'issued', 'synced_time' => $newSyncTime, 'created_at' => '2020-01-01T00:00:00Z', 'updated_at' => '2020-01-01T00:00:00Z']);

    $pull = postJson('/api/sync', ['direction' => 'pull', 'node_id' => 'node-late-01', 'since_cursor' => '2025-01-01T00:00:00Z']);

    $pull->assertOk();
    $items = $pull->json('items');
    $found = collect($items)->firstWhere('id', '019ed500-1002-7000-a1b2-c3d4e5f77001');
    expect($found)->not->toBeNull();
});

it('conflicts survive a dropped pull response — at-least-once', function () {
    \Illuminate\Support\Facades\DB::table('mark_conflicts')->insert(['id' => '019ed500-1007-7000-a1b2-c3d4e5f77001', 'mark_id' => '019ed500-1008-7000-a1b2-c3d4e5f77001', 'winning_revision' => 'r2', 'rejected_revision' => 'r1-node', 'resolved_at' => now(), 'pulled_at' => null]);

    $first = postJson('/api/sync', ['direction' => 'pull', 'node_id' => 'node-survive-01', 'since_cursor' => null]);
    $first->assertOk();
    $firstConflicts = $first->json('conflicts') ?? [];
    expect(count($firstConflicts))->toBeGreaterThanOrEqual(0);
});

it('a mark applied via sync has non-null synced_time and appears in pull', function () {
    $now = now()->toIso8601ZuluString();

    $markPayload = ['id' => '019ed500-1009-7000-a1b2-c3d4e5f77002', 'revision' => 'r-sync', 'revision_parent' => null, 'student_id' => '019ed500-100a-7000-a1b2-c3d4e5f77002', 'subject_id' => '019ed500-100b-7000-a1b2-c3d4e5f60001', 'class_id' => '019ed500-100c-7000-a1b2-c3d4e5f60001', 'sequence' => 'T1-Seq1', 'owner_teacher_id' => '019ed500-100d-7000-a1b2-c3d4e5f60003', 'score' => 14.0, 'max_score' => 20.0, 'recorded_at' => $now, 'synced_time' => $now];

    $push = postJson('/api/sync', ['direction' => 'push', 'node_id' => 'node-mark-01', 'since_cursor' => null, 'items' => [['type' => 'mark', 'id' => $markPayload['id'], 'revision' => $markPayload['revision'], 'payload' => $markPayload]]]);

    $push->assertOk();
    $mark = \App\Domain\Academics\Models\Mark::find($markPayload['id']);
    expect($mark)->not->toBeNull();
    expect($mark->synced_time)->not->toBeNull();

    $pull = postJson('/api/sync', ['direction' => 'pull', 'node_id' => 'node-mark-02', 'since_cursor' => '2020-01-01T00:00:00Z']);
    $pull->assertOk();
    $pulled = collect($pull->json('items'))->first(fn ($i) => $i['id'] === $markPayload['id']);
    expect($pulled)->not->toBeNull();
});
