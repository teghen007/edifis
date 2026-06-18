<?php

use App\Domain\Notifications\Models\FcmToken;
use App\Domain\Notifications\Notifications\ResultsPublished;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['edifis.mode' => 'cloud']);
});

use Illuminate\Support\Facades\Notification;

it('builds a v1 FCM request with Bearer token for ResultsPublished', function () {
    config(['services.fcm.project_id' => 'edifis-test-project']);
    config(['services.fcm.test_access_token' => 'ya29.test-token']);

    Http::fake([
        'https://fcm.googleapis.com/v1/projects/*' => Http::response(['name' => 'projects/test/messages/123'], 200),
    ]);

    $parent = User::create([
        'id' => tid('parent.fcm.test'),
        'name' => 'FCM Test Parent',
        'email' => 'fcm.test@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $parent->assignRole('parent');

    FcmToken::create([
        'id' => tid('fcm.token.1'),
        'user_id' => $parent->id,
        'token' => 'fcm-device-token-abc123',
    ]);

    $parent->notify(new ResultsPublished(
        studentId: tid('stu.fcm.1'),
        studentName: 'FCM Student',
        sequence: 'T1',
        average: 15.0,
    ));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v1/projects/edifis-test-project/messages:send')
            && $request->header('Authorization')[0] === 'Bearer ya29.test-token'
            && $request['message']['token'] === 'fcm-device-token-abc123'
            && $request['message']['notification']['title'] === 'Results Published — EDIFIS';
    });
});

it('the channel does not send when no FCM token is registered', function () {
    config(['services.fcm.project_id' => 'edifis-test-project']);
    config(['services.fcm.test_access_token' => 'ya29.test-token']);

    Http::fake();

    $parent = User::create([
        'id' => tid('parent.fcm.none'),
        'name' => 'No Token Parent',
        'email' => 'fcm.none@test.local',
        'password' => 'secret',
        'active' => true,
    ]);
    $parent->assignRole('parent');

    $parent->notify(new ResultsPublished(tid('stu.fcm.2'), 'Nobody', 'T1', 10.0));

    Http::assertNothingSent();
});
