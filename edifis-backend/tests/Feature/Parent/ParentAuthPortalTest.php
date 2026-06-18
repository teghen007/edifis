<?php

use App\Models\User;
use App\Domain\Auth\Models\LoginOtp;
use App\Domain\Auth\Models\TrustedDevice;
use App\Domain\Auth\Actions\TrustDevice;
use App\Domain\Notifications\Actions\PublishResults;
use App\Domain\Notifications\Notifications\ResultsPublished;
use App\Domain\Students\Models\Student;
use Illuminate\Support\Facades\Notification;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['edifis.mode' => 'cloud']);
});

it('forces PIN set on first bootstrap login', function () {
    $parent = User::create([
        'id' => tid('parent.pin.force'),
        'name' => 'PIN Parent',
        'email' => 'pin.parent@test.local',
        'phone' => '670000001',
        'password' => 'secret',
        'must_reset_credential' => true,
        'active' => true,
    ]);
    $parent->assignRole('parent');

    $response = postJson('/api/parent/login', [
        'phone' => '670000001',
        'credential' => '100000076',
        'device_token' => null,
    ]);

    $response->assertOk()
        ->assertJson(['must_reset_pin' => true]);
});

it('bootstrap credential is phone digits reversed', function () {
    $parent = User::create([
        'id' => tid('parent.boot.rev'),
        'name' => 'Bootstrap Parent',
        'email' => 'boot.parent@test.local',
        'phone' => '677123456',
        'password' => 'secret',
        'must_reset_credential' => true,
        'active' => true,
    ]);
    $parent->assignRole('parent');

    $fail = postJson('/api/parent/login', [
        'phone' => '677123456',
        'credential' => '123456',
    ]);

    $fail->assertStatus(401)->assertJson(['code' => 'invalid_credentials']);

    $ok = postJson('/api/parent/login', [
        'phone' => '677123456',
        'credential' => '654321776',
    ]);

    $ok->assertOk()->assertJson(['must_reset_pin' => true]);
});

it('new device triggers OTP, trusted device skips', function () {
    $parent = User::create([
        'id' => tid('parent.otp.flow'),
        'name' => 'OTP Parent',
        'email' => 'otp.parent@test.local',
        'phone' => '670000003',
        'password' => 'secret',
        'pin_hash' => bcrypt('1234'),
        'must_reset_credential' => false,
        'active' => true,
    ]);
    $parent->assignRole('parent');

    $first = postJson('/api/parent/login', [
        'phone' => '670000003',
        'credential' => '1234',
        'device_token' => null,
    ]);

    $first->assertOk()
        ->assertJson(['status' => 'otp_required']);

    expect(LoginOtp::where('user_id', $parent->id)->exists())->toBeTrue();

    $trust = app(TrustDevice::class);
    $deviceSecret = $trust->handle($parent, 'Test Browser');

    $second = postJson('/api/parent/login', [
        'phone' => '670000003',
        'credential' => '1234',
        'device_token' => $deviceSecret,
    ]);

    $second->assertOk()
        ->assertJsonMissing(['status' => 'otp_required']);

    expect($second->json('token'))->toBeString();
});

it('rate-limits login attempts', function () {
    $parent = User::create([
        'id' => tid('parent.rate.limit'),
        'name' => 'Rate Parent',
        'email' => 'ratelimit@test.local',
        'phone' => '670000007',
        'password' => 'secret',
        'must_reset_credential' => false,
        'active' => true,
    ]);
    $parent->assignRole('parent');

    for ($i = 0; $i < 10; $i++) {
        postJson('/api/parent/login', [
            'phone' => '670000007',
            'credential' => 'wrong',
        ]);
    }

    $response = postJson('/api/parent/login', [
        'phone' => '670000007',
        'credential' => 'wrong',
    ]);

    $response->assertStatus(429)
        ->assertJson(['code' => 'rate_limited']);
});

it('parent portal uses role gates (parent-only)', function () {
    config(['edifis.mode' => 'cloud']);

    $parent = User::create([
        'id' => tid('parent.portal.gate'),
        'name' => 'Portal Parent',
        'phone' => '670000008',
        'email' => 'portal@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $parent->assignRole('parent');

    $response = actingAs($parent)->getJson('/api/parent/children');
    $response->assertOk();
});

it('set PIN endpoint works for parent', function () {
    $parent = User::create([
        'id' => tid('parent.set.pin.ok'),
        'name' => 'Set PIN Parent',
        'phone' => '670000009',
        'email' => 'setpin@test.local',
        'password' => 'secret',
        'pin_hash' => null,
        'must_reset_credential' => true,
        'active' => true,
    ]);
    $parent->assignRole('parent');

    $response = actingAs($parent)->postJson('/api/parent/set-pin', ['pin' => '5678']);

    $response->assertOk()->assertJson(['status' => 'pin_set']);

    $parent->refresh();
    expect($parent->must_reset_credential)->toBeFalse();
    expect($parent->pin_hash)->not->toBeNull();
});

it('PublishResults dispatches exactly once via Idempotency', function () {
    Notification::fake();

    $student = Student::create([
        'id' => tid('stu.pub.test.10'),
        'given_name' => 'Publish',
        'family_name' => 'Test',
        'current_class_id' => tid('class.pub.10'),
        'enrolled_at' => now(),
    ]);

    $parent = User::create([
        'id' => tid('parent.pub.10'),
        'name' => 'Pub Parent',
        'phone' => '670000010',
        'email' => 'pub10@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $parent->assignRole('parent');

    $publish = app(PublishResults::class);

    $publish->handle($student, '2026-T1', 16.0);
    Notification::assertSentTo($parent, ResultsPublished::class, 1);

    $publish->handle($student, '2026-T1', 16.0);
    Notification::assertSentTo($parent, ResultsPublished::class, 1);
});

it('no SMS channel in any notification via()', function () {
    $classes = [
        [\App\Domain\Notifications\Notifications\ResultsPublished::class, ['test-id', 'Test', 'T1', 10.0]],
        [\App\Domain\Notifications\Notifications\FeePosted::class, ['test-id', 'Test', 5000]],
        [\App\Domain\Notifications\Notifications\AttendanceFlagged::class, ['test-id', 'Test', 3]],
        [\App\Domain\Notifications\Notifications\ExeatIssued::class, ['test-id', 'Test', 'reason']],
        [\App\Domain\Notifications\Notifications\CalendarEventPosted::class, ['Title', '2026-01-01']],
    ];

    foreach ($classes as [$class, $args]) {
        $instance = new $class(...$args);
        $via = $instance->via(new User());

        expect($via)->not->toContain('sms');
        expect($via)->not->toContain(\App\Domain\Notifications\Channels\SmsChannel::class);
        expect(array_intersect($via, ['database']))->not->toBeEmpty();
    }
});
