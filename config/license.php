<?php

return [
    /*
    |--------------------------------------------------------------------------
    | License Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the license management system
    |
    */

    'enabled' => env('LICENSE_ENABLED', true),

    'validation_url' => env('LICENSE_VALIDATION_URL', 'https://license.yourdomain.com/api/validate'),

    'require_license' => env('REQUIRE_LICENSE', true),

    'cache_ttl' => env('LICENSE_CACHE_TTL', 3600), // 1 hour

    'trial_days' => env('LICENSE_TRIAL_DAYS', 30),

    'grace_period_days' => env('LICENSE_GRACE_PERIOD', 7),

    'check_frequency' => env('LICENSE_CHECK_FREQUENCY', 24), // hours

    'encryption_key' => env('LICENSE_ENCRYPTION_KEY', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | License Types Configuration
    |--------------------------------------------------------------------------
    */

    'types' => [
        'trial' => [
            'name' => 'Trial License',
            'max_users' => 5,
            'max_installations' => 1,
            'features' => ['basic_features', 'trial_watermark'],
            'price' => 0,
            'duration_days' => 30
        ],
        'basic' => [
            'name' => 'Basic License',
            'max_users' => 10,
            'max_installations' => 1,
            'features' => ['basic_features', 'email_support'],
            'price' => 99,
            'duration_days' => 365
        ],
        'professional' => [
            'name' => 'Professional License',
            'max_users' => 50,
            'max_installations' => 3,
            'features' => ['all_features', 'phone_support', 'api_access'],
            'price' => 299,
            'duration_days' => 365
        ],
        'enterprise' => [
            'name' => 'Enterprise License',
            'max_users' => 0, // unlimited
            'max_installations' => 10,
            'features' => ['all_features', 'api_access', 'custom_integrations', 'priority_support', 'white_label'],
            'price' => 999,
            'duration_days' => 365
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain and IP Restrictions
    |--------------------------------------------------------------------------
    */

    'allowed_domains' => env('LICENSE_ALLOWED_DOMAINS', null), // comma separated

    'allowed_ips' => env('LICENSE_ALLOWED_IPS', null), // comma separated

    'block_tor' => env('LICENSE_BLOCK_TOR', true),

    'block_vpn' => env('LICENSE_BLOCK_VPN', false),

    /*
    |--------------------------------------------------------------------------
    | Security Features
    |--------------------------------------------------------------------------
    */

    'require_https' => env('LICENSE_REQUIRE_HTTPS', true),

    'max_failed_attempts' => env('LICENSE_MAX_FAILED_ATTEMPTS', 5),

    'lockout_duration' => env('LICENSE_LOCKOUT_DURATION', 3600), // 1 hour

    'enable_audit_log' => env('LICENSE_AUDIT_LOG', true),

    /*
    |--------------------------------------------------------------------------
    | Watermarking
    |--------------------------------------------------------------------------
    */

    'watermark' => [
        'enabled' => env('WATERMARK_ENABLED', true),
        'text' => env('WATERMARK_TEXT', 'UNLICENSED COPY - ' . env('APP_NAME')),
        'opacity' => env('WATERMARK_OPACITY', 0.1),
        'font_size' => env('WATERMARK_FONT_SIZE', 48),
        'color' => env('WATERMARK_COLOR', '#FF0000'),
        'position' => env('WATERMARK_POSITION', 'center'), // center, tile, diagonal
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'expiring_soon_days' => env('LICENSE_EXPIRING_SOON_DAYS', 30),
        'admin_email' => env('LICENSE_ADMIN_EMAIL', env('MAIL_FROM_ADDRESS')),
        'customer_notifications' => env('LICENSE_CUSTOMER_NOTIFICATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Protection
    |--------------------------------------------------------------------------
    */

    'api' => [
        'rate_limit' => env('LICENSE_API_RATE_LIMIT', 1000), // requests per hour
        'require_license' => env('LICENSE_API_REQUIRE_LICENSE', true),
        'allowed_origins' => env('LICENSE_API_ALLOWED_ORIGINS', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Encryption
    |--------------------------------------------------------------------------
    */

    'database_encryption' => [
        'enabled' => env('DB_ENCRYPTION_ENABLED', false),
        'key' => env('DB_ENCRYPTION_KEY', null),
        'algorithm' => env('DB_ENCRYPTION_ALGORITHM', 'AES-256-CBC'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Code Obfuscation
    |--------------------------------------------------------------------------
    */

    'obfuscation' => [
        'enabled' => env('CODE_OBFUSCATION_ENABLED', false),
        'exclude_files' => [
            'config/',
            'database/migrations/',
            'resources/views/',
            'routes/',
        ],
        'obfuscate_variables' => env('OBFUSCATE_VARIABLES', true),
        'obfuscate_functions' => env('OBFUSCATE_FUNCTIONS', true),
        'obfuscate_classes' => env('OBFUSCATE_CLASSES', true),
    ],
];