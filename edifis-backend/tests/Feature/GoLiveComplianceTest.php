<?php

use App\Domain\Migration\Actions\ValidateMigration;
use App\Domain\Provisioning\Actions\ProvisionAccount;
use App\Models\User;
use App\Domain\Monitoring\Actions\PostNodeStatus;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('provisions a staff account with a role', function () {
    $prov = app(ProvisionAccount::class);
    $user = $prov->staff('Test Teacher', 'teacher2@test.local', 'subject_teacher', 'password123');

    expect($user->exists())->toBeTrue();
    expect($user->active)->toBeTrue();
    expect($user->hasRole('subject_teacher'))->toBeTrue();
});

it('provisions a guardian account and assigns parent role', function () {
    $prov = app(ProvisionAccount::class);
    $user = $prov->guardian('Jane Doe', '+237670000002','3e090062-07b6-47ef-8487-3141514eb886');

    expect($user->exists())->toBeTrue();
    expect($user->hasRole('parent'))->toBeTrue();
});

it('generates unique claim codes', function () {
    $prov = app(ProvisionAccount::class);

    $codes = [];
    for ($i = 0; $i < 10; $i++) {
        $codes[] = $prov->generateClaimCode();
    }

    expect(count(array_unique($codes)))->toBe(10);
});

it('dry-runs migration and reports errors for invalid rows', function () {
    $mig = app(ValidateMigration::class);

    $result = $mig->dryRun([
        ['given_name' => 'Alice', 'family_name' => 'Smith'],
        ['given_name' => '', 'family_name' => ''],
        ['given_name' => 'Bob', 'family_name' => 'Jones'],
    ]);

    expect($result['total'])->toBe(3);
    expect($result['valid'])->toBe(2);
    expect(count($result['errors']))->toBe(1);
});

it('imports valid rows and skips malformed', function () {
    $mig = app(ValidateMigration::class);

    $result = $mig->import([
        ['given_name' => 'Alice', 'family_name' => 'Smith'],
        ['given_name' => '', 'family_name' => ''],
        ['given_name' => 'Bob', 'family_name' => 'Jones'],
    ]);

    expect($result['imported'])->toBe(2);
    expect($result['skipped'])->toBe(1);
    expect(count($result['rejected']))->toBe(1);
});

it('posts node status telemetry', function () {
    $response = postJson('/api/monitoring/node-status', [
        'node_id' => 'node-test-01',
        'disk_ok' => true,
        'ups_on_battery' => false,
        'pending_outbox' => 5,
    ]);

    $response->assertAccepted()
        ->assertJsonStructure([
            'node_id',
            'reported_at',
            'disk_ok',
            'ups_on_battery',
            'pending_outbox',
        ]);
});

it('go-live checklist: api/health responds', function () {
    $response = getJson('/api/health');
    // Health endpoint works even without auth
    $response->assertJsonStructure(['status', 'mode', 'version']);
});

it('go-live checklist: all roles seedable', function () {
    $expected = [
        'principal', 'vice_principal', 'bursar', 'class_master',
        'subject_teacher', 'discipline_master', 'secretary', 'parent',
    ];

    foreach ($expected as $role) {
        expect(\Spatie\Permission\Models\Role::findByName($role))->not->toBeNull();
    }
});

it('go-live checklist: issued cost is integer CFA minor units', function () {
    $item = \App\Domain\Issuance\Models\CatalogueItem::create([
        'id' =>'4e6034ee-0177-4880-a56f-2d44a87cc354',
        'name' => 'Go-Live Test Item',
        'cost' => 15000,
        'category' => 'textbook',
    ]);

    expect($item->cost)->toBeInt();
    expect($item->cost)->not->toBeFloat();
});
