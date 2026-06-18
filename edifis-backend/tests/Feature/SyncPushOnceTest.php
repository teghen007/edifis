<?php

use App\Domain\Issuance\Models\IssueEvent;
use App\Domain\Issuance\Models\CatalogueItem;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Models\AttendanceSession;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
    config(['edifis.school_code' => 'pssnkwen']);
    config(['edifis.node_id' => 'node-test-pushonce']);
    config(['sync.cloud_base_url' => 'https://cloud.edifis.test/api']);
    config(['edifis.idempotency_table' => 'idempotency_log']);
});

it('pushes each local record exactly once via markPushed', function () {
    CatalogueItem::create([
        'id' => tid('cat.push.reg'),
        'name' => 'Push Reg Test',
        'cost' => 3000,
        'category' => 'textbook',
    ]);

    $e1 = IssueEvent::create([
        'id' => tid('issue.push.r1'),
        'revision' => 'r1',
        'student_id' => tid('stu.push.r1'),
        'catalogue_item_id' => tid('cat.push.reg'),
        'cost' => 3000, 'issued_at' => now(),
        'staff_id' => tid('user.bursar'),
        'signature_ref' => 'sig-r1',
        'batch_id' => tid('batch.push.r1'),
        'device_id' => 'node-test',
        'status' => 'issued',
    ]);

    $e2 = IssueEvent::create([
        'id' => tid('issue.push.r2'),
        'revision' => 'r1',
        'student_id' => tid('stu.push.r2'),
        'catalogue_item_id' => tid('cat.push.reg'),
        'cost' => 3000, 'issued_at' => now(),
        'staff_id' => tid('user.bursar'),
        'signature_ref' => 'sig-r2',
        'batch_id' => tid('batch.push.r2'),
        'device_id' => 'node-test',
        'status' => 'issued',
    ]);

    // Both unsynced
    expect(IssueEvent::find(tid('issue.push.r1'))->synced_time)->toBeNull();
    expect(IssueEvent::find(tid('issue.push.r2'))->synced_time)->toBeNull();

    // Simulate what edifis:sync --push-only does after a successful cloud POST
    $items = [
        ['type' => 'issue_event', 'id' => tid('issue.push.r1')],
        ['type' => 'issue_event', 'id' => tid('issue.push.r2')],
    ];

    $cmd = new \App\Console\Commands\SyncCommand();
    $ref = new ReflectionClass($cmd);
    $markPushed = $ref->getMethod('markPushed');
    $collectUnsynced = $ref->getMethod('collectUnsyncedItems');

    $markPushed->invoke($cmd, $items);

    // After markPushed, records have synced_time stamped
    expect(IssueEvent::find(tid('issue.push.r1'))->synced_time)->not->toBeNull();
    expect(IssueEvent::find(tid('issue.push.r2'))->synced_time)->not->toBeNull();

    // collectUnsyncedItems now returns empty (nothing with null synced_time)
    $remaining = $collectUnsynced->invoke($cmd, null);
    expect(count($remaining))->toBe(0);
});

it('multiple cycles do not re-push', function () {
    IssueEvent::create([
        'id' => tid('issue.dedup.r1'),
        'revision' => 'r1',
        'student_id' => tid('stu.dedup.r1'),
        'catalogue_item_id' => tid('cat.push.reg'),
        'cost' => 1000, 'issued_at' => now(),
        'staff_id' => tid('user.bursar'),
        'signature_ref' => 'sig-dedup-r1',
        'batch_id' => tid('batch.dedup.r1'),
        'device_id' => 'node-test',
        'status' => 'issued',
    ]);

    $cmd = new \App\Console\Commands\SyncCommand();
    $ref = new ReflectionClass($cmd);
    $markPushed = $ref->getMethod('markPushed');
    $collectUnsynced = $ref->getMethod('collectUnsyncedItems');

    // 3 cycles
    for ($i = 0; $i < 3; $i++) {
        $items = $collectUnsynced->invoke($cmd, null);
        if (count($items) > 0) {
            $markPushed->invoke($cmd, $items);
        }
    }

    // After cycles, nothing left to push
    $remaining = $collectUnsynced->invoke($cmd, null);
    expect(count($remaining))->toBe(0);

    // Only one record was created — it should have been pushed exactly once
    expect(IssueEvent::find(tid('issue.dedup.r1'))->synced_time)->not->toBeNull();
});
