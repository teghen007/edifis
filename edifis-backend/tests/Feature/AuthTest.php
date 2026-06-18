<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
    config(['edifis.sanctum_token_ttl_minutes' => 120]);

    User::create([
        'id' =>'450b6c56-35cf-4a1e-8717-c6c71361a47b',
        'name' => 'Test Teacher',
        'email' => 'teacher@pssnkwen.local',
        'password' => 'secret',
        'active' => true,
    ])->assignRole('subject_teacher');
});

it('issues a token for valid credentials', function () {
    $response = postJson('/api/auth/login', [
        'identifier' => 'teacher@pssnkwen.local',
        'password' => 'secret',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'expires_at', 'role', 'user_id'])
        ->assertJson(['role' => 'subject_teacher']);
});

it('rejects invalid credentials', function () {
    $response = postJson('/api/auth/login', [
        'identifier' => 'teacher@pssnkwen.local',
        'password' => 'wrong',
    ]);

    $response->assertStatus(401)
        ->assertJson(['code' => 'invalid_credentials']);
});

it('rejects deactivated accounts', function () {
    User::where('email', 'teacher@pssnkwen.local')->update(['active' => false]);

    $response = postJson('/api/auth/login', [
        'identifier' => 'teacher@pssnkwen.local',
        'password' => 'secret',
    ]);

    $response->assertStatus(401)
        ->assertJson(['code' => 'account_deactivated']);
});

it('returns revoked token IDs from the revocation list', function () {
    $user = User::where('email', 'teacher@pssnkwen.local')->first();

    $revoke = app(\App\Domain\Auth\Actions\RevokeUser::class);
    $revoke->handle($user, 'test');

    $response = getJson('/api/auth/revocations');

    $response->assertOk()
        ->assertJsonStructure(['revoked_token_ids', 'revoked_user_ids', 'as_of'])
        ->assertJsonFragment(['revoked_user_ids' => [$user->id]]);
});

it('returns token revoked for known revocation', function () {
    $user = User::create([
        'id' =>'c2226ff5-8c4c-4fca-b8c7-684dabbd7904',
        'name' => 'Revoked User',
        'email' => 'revoked@test.local',
        'password' => 'secret',
        'active' => true,
    ]);

    $revoke = app(\App\Domain\Auth\Actions\RevokeUser::class);
    $revoke->handle($user, 'test');

    $response = getJson('/api/auth/revocations');
    $response->assertJsonFragment(['revoked_user_ids' => [$user->id]]);
});
