<?php

use App\Domain\Issuance\Models\CatalogueItem;
use App\Domain\Issuance\Models\IssueEvent;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Issuance\Actions\IssueItemsToStudent;
use App\Domain\Issuance\Actions\ReturnItem;
use App\Models\User;
use function Pest\Laravel\getJson;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['edifis.mode' => 'local']);

    CatalogueItem::create([
        'id' => tid('cat.test'),
        'name' => 'Test Textbook',
        'cost' => 10000,
        'category' => 'textbook',
    ]);
});

it('balance equals SUM of ledger entries and is a PHP int', function () {
    LedgerEntry::create([
        'id' => tid('ledger.debit.1'),
        'student_id' => tid('stu.bal.1'),
        'source_event_id' => tid('event.debit.1'),
        'amount' => 10000,
        'posted_at' => now(),
    ]);

    LedgerEntry::create([
        'id' => tid('ledger.credit.1'),
        'student_id' => tid('stu.bal.1'),
        'source_event_id' => tid('event.credit.1'),
        'amount' => -3000,
        'posted_at' => now(),
    ]);

    $balance = LedgerEntry::where('student_id', tid('stu.bal.1'))->sum('amount');
    expect((int) $balance)->toBe(7000);
    // Regression: BalanceQuery returns a PHP int even on PostgreSQL
    $query = app(\App\Domain\Ledger\Queries\BalanceQuery::class);
    $result = $query->get(tid('stu.bal.1'));
    expect($result['balance'])->toBeInt()->toBe(7000);
});

it('returns balance via API', function () {
    LedgerEntry::create([
        'id' => tid('ledger.api.1'),
        'student_id' => tid('stu.bal.api'),
        'source_event_id' => tid('event.api.1'),
        'amount' => 5000,
        'posted_at' => now(),
    ]);

    $parent = User::create([
        'id' => tid('user.parent.bal'),
        'name' => 'Parent Test',
        'email' => 'parent@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $parent->assignRole('parent');

    $response = actingAs($parent)
        ->getJson('/api/fees/students/' . tid('stu.bal.api') . '/balance');

    $response->assertOk()
        ->assertJson([
            'student_id' => tid('stu.bal.api'),
            'balance' => 5000,
            'currency' => 'XAF',
        ]);
});

it('return credits the ledger without touching the original', function () {
    $original = IssueEvent::create([
        'id' => tid('issue.return.test'),
        'revision' => 'r1',
        'student_id' => tid('stu.return.test'),
        'catalogue_item_id' => tid('cat.test'),
        'cost' => 8000,
        'issued_at' => now(),
        'staff_id' => tid('user.bursar'),
        'signature_ref' => 'sig-1',
        'batch_id' => tid('batch.return'),
        'status' => 'issued',
    ]);

    LedgerEntry::create([
        'id' => tid('ledger.return.debit'),
        'student_id' => tid('stu.return.test'),
        'source_event_id' => $original->id,
        'amount' => 8000,
        'posted_at' => now(),
    ]);

    $return = app(ReturnItem::class)->handle(
        issueEventId: $original->id,
        reason: 'Book returned in good condition',
        staffId: tid('user.bursar'),
    );

    $original->refresh();
    expect($original->status)->toBe('issued');

    $balance = LedgerEntry::where('student_id', tid('stu.return.test'))->sum('amount');
    expect((int) $balance)->toBe(0);
});
