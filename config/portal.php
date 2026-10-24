<?php

return [
    'currency' => env('PORTAL_CURRENCY', 'DOP'),
    'require_staff_two_factor' => env('PORTAL_REQUIRE_STAFF_2FA', true),
    'token_days' => 90,
    'document_scan_binary' => env('DOCUMENT_SCAN_BINARY'),
    'document_max_kb' => 10240,
    'document_batch_max_kb' => 20480,
    'document_batch_max_files' => 5,
    'document_order_max_bytes' => 104857600,
    'document_account_max_bytes' => 1073741824,
    'webhook_allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', env('WEBHOOK_ALLOWED_HOSTS', ''))))),
];
