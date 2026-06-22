<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials_path' => env('FCM_CREDENTIALS_PATH', storage_path('app/firebase/service-account.json')),
        'test_access_token' => env('FCM_TEST_ACCESS_TOKEN'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'deepseek'),
        'base_url' => env('AI_BASE_URL', 'https://api.deepseek.com'),
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'deepseek-chat'),
        'timeout' => (int) env('AI_TIMEOUT', 30),
    ],

];
