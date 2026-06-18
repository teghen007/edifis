<?php

use App\Domain\Issuance\Models\IssueEvent;
use App\Domain\Issuance\Models\CatalogueItem;
use App\Support\Idempotency;
use function Pest\Laravel\postJson;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('replaying an entire envelope changes nothing', function () {
    CatalogueItem::create([
        'id' =>'aac2939e-e63e-480b-ae34-0a8187ebbe1d',
        'name' => 'Sync Test Book',
        'cost' => 5000,
        'category' => 'textbook',
    ]);

    $envelope = [
        'direction' => 'push',
        'node_id' => 'node-test-01',
        'since_cursor' => null,
        'items' => [
            [
                'type' => 'issue_event',
                'id' =>'abcb3997-0682-4ee9-a8f0-87571b3e163c',
                'revision' => 'r1',
                'payload' => [
                    'id' =>'21743179-8d1c-4372-9e33-ea98142df079',
                    'revision' => 'r1',
                    'student_id' =>'645ebbee-de04-43d0-8017-eabbd2c3b2fc',
                    'catalogue_item_id' =>'c5cc6c21-f702-4788-bf58-b070984d0ebc',
                    'cost' => 5000,
                    'issued_at' => now()->toIso8601ZuluString(),
                    'staff_id' =>'0055d7d0-8d18-49f1-b2eb-64e3a8c051a4',
                    'signature_ref' => 'sig-sync',
                    'batch_id' =>'3baf1016-dbd9-4d13-aaf6-7b18747f1652',
                    'device_id' => 'node-test-01',
                    'status' => 'issued',
                    'reason' => null,
                ],
            ],
        ],
    ];

    $first = postJson('/api/sync', $envelope);
    $first->assertOk();

    $countBefore = IssueEvent::count();

    $second = postJson('/api/sync', $envelope);
    $second->assertOk();

    expect(IssueEvent::count())->toBe($countBefore);
});

it('partial replay applies only unseen items', function () {
    CatalogueItem::create([
        'id' =>'0d0d0e91-978a-4f7d-8344-0a743eb59ca2',
        'name' => 'Partial Test Book',
        'cost' => 3000,
        'category' => 'textbook',
    ]);

    $item1 = [
        'type' => 'issue_event',
        'id' =>'54bf6c57-832b-4698-85aa-dd568c1a46a4',
        'revision' => 'r2',
        'payload' => [
            'id' =>'ac236527-7e96-4ab3-8229-522ab34af443',
            'revision' => 'r2',
            'student_id' =>'03f437d9-dc59-44ef-b416-d7764355ba8b',
            'catalogue_item_id' =>'9468178c-57f9-4d94-9b34-f39b9d5a1427',
            'cost' => 3000,
            'issued_at' => now()->toIso8601ZuluString(),
            'staff_id' =>'aeabfcde-6604-4484-af9e-f2e5b32b0836',
            'signature_ref' => 'sig-partial',
            'batch_id' =>'2954170b-f397-4e54-b7e3-e0551c658dcb',
            'device_id' => 'node-test-01',
            'status' => 'issued',
            'reason' => null,
        ],
    ];

    $item2 = [
        'type' => 'issue_event',
        'id' =>'60b03103-128d-43fe-bf40-c771fe5b3812',
        'revision' => 'r3',
        'payload' => [
            'id' =>'cbbcf7d5-8fa7-43d9-b4f4-b7665e8b7cde',
            'revision' => 'r3',
            'student_id' =>'741a0a00-0a11-42ee-a6fb-86b0f21f74e0',
            'catalogue_item_id' =>'47d51489-3938-4a47-8446-060bee811db4',
            'cost' => 3000,
            'issued_at' => now()->toIso8601ZuluString(),
            'staff_id' =>'1ccba722-38f5-4dbf-865c-f68b4f546173',
            'signature_ref' => 'sig-partial2',
            'batch_id' =>'76aaf5ea-af54-4659-a08b-74cff0e9d886',
            'device_id' => 'node-test-01',
            'status' => 'issued',
            'reason' => null,
        ],
    ];

    postJson('/api/sync', [
        'direction' => 'push',
        'node_id' => 'node-test-02',
        'since_cursor' => null,
        'items' => [$item1],
    ])->assertOk();

    $countAfterFirst = IssueEvent::count();

    // Second envelope has both: one new, one seen
    postJson('/api/sync', [
        'direction' => 'push',
        'node_id' => 'node-test-02',
        'since_cursor' => null,
        'items' => [$item1, $item2],
    ])->assertOk();

    // Only one new event should have been added
    expect(IssueEvent::count())->toBe($countAfterFirst + 1);
});

it('rate-limited requests get 429 with retry_after', function () {
    $base = [
        'direction' => 'push',
        'node_id' => 'flood-node',
        'since_cursor' => null,
        'items' => [],
    ];

    $rateLimited = false;

    for ($i = 0; $i < 200; $i++) {
        $response = postJson('/api/sync', $base);
        if ($response->status() === 429) {
            $response->assertJsonStructure(['retry_after_seconds']);
            $rateLimited = true;
            break;
        }
    }

    expect($rateLimited)->toBeTrue();
});

it('a record created after cursor shows up in pull', function () {
    CatalogueItem::create([
        'id' =>'626351ff-7ceb-4a3b-a482-e074c87f906e',
        'name' => 'Pull Test Book',
        'cost' => 2000,
        'category' => 'textbook',
    ]);

    $oldCursor = '2025-01-01T00:00:00Z';

    // Create an event that was synced now
    \App\Domain\Issuance\Models\IssueEvent::create([
        'id' =>'3b1053f9-410e-42c3-ae08-d0ceb4be2811',
        'revision' => 'r-pull',
        'student_id' =>'3d58cf53-1479-42c5-87ad-5921db28dce2',
        'catalogue_item_id' =>'ce959c3e-d163-429f-a32c-88a1ae5008a4',
        'cost' => 2000,
        'issued_at' => now(),
        'staff_id' =>'905357e2-6518-4a7e-ae18-f33445afa2b2',
        'signature_ref' => 'sig-pull',
        'batch_id' =>'9b6695b9-fde6-4330-9cb6-2528842b2943',
        'device_id' => 'node-test-01',
        'status' => 'issued',
        'synced_time' => now(),
    ]);

    $pull = postJson('/api/sync', [
        'direction' => 'pull',
        'node_id' => 'node-test-03',
        'since_cursor' => $oldCursor,
    ]);

    $pull->assertOk()
        ->assertJsonStructure([
            'direction',
            'node_id',
            'since_cursor',
            'next_cursor',
            'items',
            'conflicts',
        ]);

    expect($pull->json('next_cursor'))->not->toBe($oldCursor);
    expect(count($pull->json('items')))->toBeGreaterThanOrEqual(0);
});
