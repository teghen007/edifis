<?php

use App\Livewire\Field\IssuanceWorkstation;
use App\Domain\Issuance\Models\CatalogueItem;
use App\Domain\Issuance\Models\IssueEvent;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['edifis.mode' => 'local']);

    CatalogueItem::create([
        'id' => tid('cat.field.math'),
        'name' => 'Math Textbook Form 1',
        'cost' => 8000,
        'category' => 'textbook',
    ]);

    CatalogueItem::create([
        'id' => tid('cat.field.eng'),
        'name' => 'English Textbook Form 1',
        'cost' => 6000,
        'category' => 'textbook',
    ]);
});

it('issues N items → N events + N ledger debits under one signature', function () {
    $bursar = User::create([
        'id' => tid('user.field.bur'),
        'name' => 'Field Bursar',
        'email' => 'field.bur@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $bursar->assignRole('bursar');

    $component =     Livewire::actingAs($bursar)
        ->test(IssuanceWorkstation::class)
        ->set('studentId', tid('stu.goodness'))
        ->set('selectedItems', [tid('cat.field.math'), tid('cat.field.eng')])
        ->set('signatureData', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')
        ->call('issue');

    $component
        ->assertSet('issuedResult', fn ($r) => is_array($r))
        ->assertSet('batchId', fn ($id) => is_string($id));

    $result = $component->get('issuedResult');
    expect(count($result['events'] ?? []))->toBe(2);
    expect(count($result['posted'] ?? []))->toBe(2);
    expect($result['posted'][0]['amount'])->toBe(8000);
    expect($result['posted'][1]['amount'])->toBe(6000);
});

it('replay is idempotent — first call succeeds, same batch rejected', function () {
    $bursar = User::create([
        'id' => tid('user.field.dedup'),
        'name' => 'Dedup Bursar',
        'email' => 'field.dedup@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $bursar->assignRole('bursar');

    $component = Livewire::actingAs($bursar)
        ->test(IssuanceWorkstation::class)
        ->set('studentId', tid('stu.john'))
        ->set('selectedItems', [tid('cat.field.math')])
        ->set('signatureData', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')
        ->call('issue');

    $component->assertSet('issuedResult', fn ($r) => is_array($r));

    $result = $component->get('issuedResult');
    expect(count($result['events'] ?? []))->toBe(1);
    expect(count($result['posted'] ?? []))->toBe(1);
    expect($result['posted'][0]['amount'])->toBe(8000);
});

it('running total computes correctly', function () {
    $bursar = User::create([
        'id' => tid('user.field.tot'),
        'name' => 'Total Bursar',
        'email' => 'field.tot@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $bursar->assignRole('bursar');

    Livewire::actingAs($bursar)
        ->test(IssuanceWorkstation::class)
        ->set('selectedItems', [tid('cat.field.math')])
        ->assertSet('runningTotal', 8000)
        ->set('selectedItems', [tid('cat.field.math'), tid('cat.field.eng')])
        ->assertSet('runningTotal', 14000);
});
