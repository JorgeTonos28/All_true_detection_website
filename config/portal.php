<?php

return [
    'currency' => env('PORTAL_CURRENCY', 'DOP'),
    'require_staff_two_factor' => env('PORTAL_REQUIRE_STAFF_2FA', true),
    'token_days' => 90,
    'document_scan_binary' => env('DOCUMENT_SCAN_BINARY'),
    'webhook_allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', env('WEBHOOK_ALLOWED_HOSTS', ''))))),
];
