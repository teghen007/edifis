<?php

use App\Domain\Issuance\Models\CatalogueItem;
use App\Domain\Issuance\Models\IssueEvent;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Models\User;
use function Pest\Laravel\postJson;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['edifis.mode' => 'local']);

    CatalogueItem::create([
        'id' => tid('cat.math'),
        'name' => 'Mathematics Textbook Form 1',
        'cost' => 8000,
        'category' => 'textbook',
    ]);

    CatalogueItem::create([
        'id' => tid('cat.english'),
        'name' => 'English Textbook Form 1',
        'cost' => 6000,
        'category' => 'textbook',
    ]);
});

it('issues items and posts ledger debits', function () {
    $bursar = User::create([
        'id' => tid('user.bursar'),
        'name' => 'Bursar Test',
        'email' => 'bursar@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $bursar->assignRole('bursar');

    $response = actingAs($bursar)->postJson('/api/issuance/issue', [
        'batch_id' => tid('batch.1'),
        'student_id' => tid('stu.goodness'),
        'signature_ref' => 'sig-test-batch-001',
        'items' => [
            ['catalogue_item_id' => tid('cat.math')],
            ['catalogue_item_id' => tid('cat.english')],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['events', 'posted']);

    $data = $response->json();
    expect(count($data['events']))->toBe(2);
    expect(count($data['posted']))->toBe(2);
    expect($data['posted'][0]['amount'])->toBe(8000);
    expect($data['posted'][1]['amount'])->toBe(6000);
});

it('replays idempotently and writes nothing extra', function () {
    $bursar = User::create([
        'id' => tid('user.bursar.idem'),
        'name' => 'Bursar Idem',
        'email' => 'bursaridem@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $bursar->assignRole('bursar');

    $batchId = tid('batch.2');

    actingAs($bursar)->postJson('/api/issuance/issue', [
        'batch_id' => $batchId,
        'student_id' => tid('stu.john'),
        'signature_ref' => 'sig-test-batch-002',
        'items' => [
            ['catalogue_item_id' => tid('cat.math')],
        ],
    ]);

    $issueEventCount = IssueEvent::count();
    $ledgerCount = LedgerEntry::count();

    $replay = actingAs($bursar)->postJson('/api/issuance/issue', [
        'batch_id' => $batchId,
        'student_id' => tid('stu.john'),
        'signature_ref' => 'sig-test-batch-002',
        'items' => [
            ['catalogue_item_id' => tid('cat.math')],
        ],
    ]);

    $replay->assertOk()
        ->assertJson(['code' => 'idempotency_replay']);

    expect(IssueEvent::count())->toBe($issueEventCount);
    expect(LedgerEntry::count())->toBe($ledgerCount);
});
