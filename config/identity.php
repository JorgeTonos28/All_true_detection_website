<?php

return [
    // Credentials belong to Laravel's environment, never to business settings or a browser.
    'didit' => [
        'api_key' => env('DIDIT_API_KEY'),
        'webhook_secret' => env('DIDIT_WEBHOOK_SECRET'),
        'application_id' => env('DIDIT_APPLICATION_ID'),
        'workflow_id' => env('DIDIT_WORKFLOW_ID'),
        'environment' => env('DIDIT_ENVIRONMENT', 'sandbox'),
        'production_approved' => (bool) env('DIDIT_PRODUCTION_APPROVED', false),
        'privacy_approved' => (bool) env('DIDIT_PRIVACY_APPROVED', false),
    ],
    'notice_version' => 'didit-2026-10-v1',
    'daily_session_limit' => (int) env('DIDIT_DAILY_SESSION_LIMIT', 200),
    'account_daily_session_limit' => (int) env('DIDIT_ACCOUNT_DAILY_SESSION_LIMIT', 20),
];
