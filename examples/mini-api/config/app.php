<?php

return [
    'timezone' => 'Asia/Tehran',
    'log_path' => __DIR__ . '/../storage/logs/error.log',

    // این تنظیمات مستقیماً به session_set_cookie_params پاس داده می‌شود.
    // secure=false فقط برای توسعه‌ی محلی (HTTP) است؛ در Production حتماً true باشد.
    'session_cookie' => [
        'secure' => false,
        'samesite' => 'Lax',
    ],

    // دامنه‌هایی که اجازه دارند از طریق مرورگر (با کوکی) به این API متصل شوند
    'cors_allowed_origins' => [
        'http://localhost:3000',
    ],
];
