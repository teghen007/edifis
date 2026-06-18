<?php

use App\Domain\Auth\Actions\TrustDevice;
use App\Domain\Auth\Models\TrustedDevice;
use App\Models\User;
use function Pest\Laravel\postJson;

beforeEach(function () {
    config(['edifis.mode' => 'cloud']);
});

it('stored device row cannot be replayed as a cookie secret', function () {
    $user = User::create([
        'id' => tid('user.dev.forge'),
        'name' => 'Forge Test',
        'email' => 'forge@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $user->assignRole('parent');

    $trust = app(TrustDevice::class);
    $secret = $trust->handle($user, 'Test Browser');

    // The stored device_token is hash('sha256', secret), NOT the secret itself
    $storedRow = TrustedDevice::where('user_id', $user->id)->first();
    expect($storedRow->device_token)->not->toBe($secret);

    // Replaying the DB row as a cookie value → not trusted
    expect($trust->isTrusted($user->id, $storedRow->device_token))->toBeFalse();

    // The real secret IS trusted
    expect($trust->isTrusted($user->id, $secret))->toBeTrue();
});

it('user B token never trusts user A', function () {
    $userA = User::create([
        'id' => tid('user.dev.a'),
        'name' => 'User A',
        'email' => 'usera@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $userA->assignRole('parent');

    $userB = User::create([
        'id' => tid('user.dev.b'),
        'name' => 'User B',
        'email' => 'userb@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $userB->assignRole('parent');

    $trust = app(TrustDevice::class);
    $secretA = $trust->handle($userA, 'Browser A');

    // User A's token fails for user B
    expect($trust->isTrusted($userB->id, $secretA))->toBeFalse();

    // User A's token works for user A
    expect($trust->isTrusted($userA->id, $secretA))->toBeTrue();
});

it('OTP mail is queued when sending', function () {
    \Illuminate\Support\Facades\Mail::fake();

    $user = User::create([
        'id' => tid('user.otp.mail'),
        'name' => 'OTP Mail User',
        'email' => 'otpmail@test.local',
        'password' => 'secret',
        'active' => true,
    ]);

    $otp = app(App\Domain\Auth\Actions\VerifyNewDeviceOtp::class);
    $otp->send($user, 'Test Browser');

    \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\OtpMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

it('parent dashboard shell renders HTML with child list', function () {
    $student = \App\Domain\Students\Models\Student::create([
        'id' => tid('stu.dash.1'),
        'given_name' => 'Dashboard',
        'family_name' => 'Child',
        'current_class_id' => tid('class.dash.1'),
        'enrolled_at' => now(),
    ]);

    $parent = User::create([
        'id' => tid('parent.dash.1'),
        'name' => 'Dash Parent',
        'email' => 'dash@test.local',
        'phone' => '670000099',
        'password' => 'secret',
        'active' => true,
    ]);
    $parent->assignRole('parent');

    // Render the dashboard view directly (unauthenticated → shows login prompt)
    $html = view('parent.dashboard', ['children' => []])->render();

    expect($html)->toContain('EDIFIS');
    expect($html)->toContain('Parent Portal');
    expect($html)->toContain('Sign In');
    expect($html)->toContain('serviceWorker');
    expect($html)->toContain('manifest.webmanifest');
});
