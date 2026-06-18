<?php

use App\Models\User;
use App\Domain\Audit\Models\AuditEntry;
use function Pest\Laravel\postJson;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('VACUUM query never mutates � read only', function () {
    $principal = User::create([
        'id' => '018f3c2a-6000-7000-a1b2-c3d4e5f60001',
        'name' => 'Principal V',
        'email' => 'principal.v@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $principal->assignRole('principal');

    $auditBefore = AuditEntry::count();

    $response = actingAs($principal)->postJson('/api/vacuum/query', [
        'question' => 'Who is borderline for promotion in Form 4?',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['answer', 'records']);

    expect(AuditEntry::count())->toBe($auditBefore); // No audit entries for reads
});

it('VACUUM query rejects non-principal', function () {
    $teacher = User::create([
        'id' => '018f3c2a-6000-7000-a1b2-c3d4e5f60001',
        'name' => 'Teacher V',
        'email' => 'teacher.v@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $teacher->assignRole('subject_teacher');

    $response = actingAs($teacher)->postJson('/api/vacuum/query', [
        'question' => 'Show me all marks',
    ]);

    $response->assertOk()
        ->assertJson(['answer' => 'Access denied. VACUUM queries require the Principal role.']);
});

it('VACUUM command deactivate_account writes audit and requires confirm', function () {
    $principal = User::create([
        'id' => '018f3c2a-6000-7000-a1b2-c3d4e5f60001',
        'name' => 'Principal Cmd',
        'email' => 'principal.cmd@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $principal->assignRole('principal');

    $target = User::create([
        'id' => '018f3c2a-6000-7000-a1b2-c3d4e5f60009',
        'name' => 'To Deactivate',
        'email' => 'deactivate@test.local',
        'password' => 'secret',
        'active' => true,
    ]);

    // Without confirm ? validation_failed
    $fail = actingAs($principal)->postJson('/api/vacuum/command', [
        'command' => 'deactivate_account',
        'target' => ['type' => 'account', 'account_id' => $target->id],
        'reason' => 'Disciplinary action',
        'confirm' => false,
    ]);

    $fail->assertStatus(422)
        ->assertJson(['code' => 'validation_failed']);

    // With confirm ? succeeds
    $auditBefore = AuditEntry::count();

    $response = actingAs($principal)->postJson('/api/vacuum/command', [
        'command' => 'deactivate_account',
        'target' => ['type' => 'account', 'account_id' => $target->id],
        'reason' => 'Disciplinary action',
        'confirm' => true,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['applied', 'audit']);

    expect(AuditEntry::count())->toBeGreaterThan($auditBefore);

    $target->refresh();
    expect($target->active)->toBeFalse();
    expect($target->exists())->toBeTrue();
});

it('VACUUM audit carries reason and true old?new', function () {
    $principal = User::create([
        'id' => '018f3c2a-6000-7000-a1b2-c3d4e5f60001',
        'name' => 'Principal Reason',
        'email' => 'principal.reason@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $principal->assignRole('principal');

    $target = User::create([
        'id' => '018f3c2a-6000-7000-a1b2-c3d4e5f60009',
        'name' => 'Reason Target',
        'email' => 'reason.target@test.local',
        'password' => 'secret',
        'active' => true,
    ]);

    $response = actingAs($principal)->postJson('/api/vacuum/command', [
        'command' => 'deactivate_account',
        'target' => ['type' => 'account', 'account_id' => $target->id],
        'reason' => 'Breach of conduct � disciplinary board decision',
        'confirm' => true,
    ]);

    $response->assertOk();

    $audit = AuditEntry::where('entity_id', $target->id)
        ->where('action', 'vacuum.deactivate_account')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->before)->toBeArray();
    expect($audit->before['active'])->toBeTrue();
    expect($audit->after)->toBeArray();
    expect($audit->after['active'])->toBeFalse();
    expect($audit->actor_role)->toBe('principal');
});

it('VACUUM command rejects finance target', function () {
    $principal = User::create([
        'id' => '018f3c2a-6000-7000-a1b2-c3d4e5f60001',
        'name' => 'Principal Fin',
        'email' => 'principal.fin@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $principal->assignRole('principal');

    $response = actingAs($principal)->postJson('/api/vacuum/command', [
        'command' => 'correct_mark',
        'target' => ['type' => 'ledger_entry', 'id' => 'any'],
        'reason' => 'test',
        'confirm' => false,
    ]);

    $response->assertStatus(403)
        ->assertJson(['code' => 'forbidden']);
});

it('VACUUM command non-principal ? forbidden', function () {
    $teacher = User::create([
        'id' => '018f3c2a-6000-7000-a1b2-c3d4e5f60001',
        'name' => 'Teacher Fin',
        'email' => 'teacher.fin@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $teacher->assignRole('subject_teacher');

    $response = actingAs($teacher)->postJson('/api/vacuum/command', [
        'command' => 'deactivate_account',
        'target' => ['type' => 'account', 'account_id' => 'any'],
        'reason' => 'test',
        'confirm' => false,
    ]);

    $response->assertStatus(403);
});
