<?php

return [
    'length' => 6,
    'expiry_minutes' => 5,
    'max_attempts' => 5,
    'resend_cooldown_seconds' => 60,
    'request_limit_per_mobile' => 3,
    'request_limit_per_ip' => 10,
    'request_limit_window_seconds' => 900,
    'verify_limit_per_mobile' => 10,
    'sender' => env('MOBILE_OTP_SENDER', 'null'),
];
