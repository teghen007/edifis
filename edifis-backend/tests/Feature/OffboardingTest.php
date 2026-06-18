<?php

use App\Models\User;
use App\Domain\Auth\Actions\RevokeUser;
use App\Domain\Auth\Services\RevocationList;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('disables a user and records revocation', function () {
    $user = User::create([
        'id' =>'fbcc7821-ab69-46ec-a35c-daf4bd633105',
        'name' => 'Offboarded',
        'email' => 'offboarded@test.local',
        'password' => 'secret',
        'active' => true,
    ]);

    $revoke = app(RevokeUser::class);
    $revoke->handle($user, 'disciplinary');

    $user->refresh();
    expect($user->active)->toBeFalse();

    $revocations = app(RevocationList::class)->revokedSince(null);
    expect($revocations['revoked_user_ids'])->toContain($user->id);

    $response = postJson('/api/auth/login', [
        'identifier' => 'offboarded@test.local',
        'password' => 'secret',
    ]);

    $response->assertStatus(401)
        ->assertJson(['code' => 'account_deactivated']);
});

it('is idempotent on repeated revocation', function () {
    $user = User::create([
        'id' =>'ad5f8f4f-9f2c-4c27-825b-f4e6407b5a1c',
        'name' => 'Idempotent',
        'email' => 'idempotent@test.local',
        'password' => 'secret',
        'active' => true,
    ]);

    $revoke = app(RevokeUser::class);
    $revoke->handle($user, 'first');
    $revoke->handle($user, 'second');

    $revocations = app(RevocationList::class)->revokedSince(null);
    $count = collect($revocations['revoked_user_ids'])
        ->filter(fn ($id) => $id === $user->id)
        ->count();

    expect($count)->toBe(1);
});

it('retains authored records after offboarding', function () {
    $user = User::create([
        'id' =>'473f7ad7-1895-433d-ad11-c2909be9525a',
        'name' => 'Retain Test',
        'email' => 'retain@test.local',
        'password' => 'secret',
        'active' => true,
    ]);

    $revoke = app(RevokeUser::class);
    $revoke->handle($user, 'resigned');

    $user->refresh();
    expect($user->exists())->toBeTrue();
    expect($user->active)->toBeFalse();
});
