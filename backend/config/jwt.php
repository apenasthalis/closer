<?php

return [
    'secret' => env('JWT_SECRET'),
    'algorithm' => 'HS256',
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),
    'audience' => env('JWT_AUDIENCE', 'closer-os'),
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 300),
    'refresh_ttl_days' => (int) env('JWT_REFRESH_TTL_DAYS', 14),
    'leeway' => (int) env('JWT_LEEWAY', 30),
    'cookie_name' => env('JWT_REFRESH_COOKIE', 'closer_refresh'),
    'cookie_secure' => filter_var(env('JWT_COOKIE_SECURE', false), FILTER_VALIDATE_BOOL),
    'cookie_same_site' => env('JWT_COOKIE_SAME_SITE', 'lax'),
    'minimum_secret_length' => 32,
];
