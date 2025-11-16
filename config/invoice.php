<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice Number Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration settings for invoice numbering system.
    | All settings are customizable and can be overridden via environment variables.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Invoice Number Pattern Settings
    |--------------------------------------------------------------------------
    |
    | Define the structure and components of invoice numbers.
    |
    */

    'pattern' => [
        'prefix' => env('INVOICE_PREFIX', 'INV'),
        'separator' => env('INVOICE_SEPARATOR', '-'),
        'include_year' => env('INVOICE_INCLUDE_YEAR', true),
        'include_month' => env('INVOICE_INCLUDE_MONTH', false),
        'suffix' => env('INVOICE_SUFFIX', ''),
        'padding' => env('INVOICE_PADDING', 6), // Number of digits to pad the sequence
    ],

    /*
    |--------------------------------------------------------------------------
    | Sequencing and Numbering Settings
    |--------------------------------------------------------------------------
    |
    | Control how invoice numbers are generated and incremented.
    |
    */

    'sequencing' => [
        'start_number' => env('INVOICE_START_NUMBER', 1),
        'increment_by' => env('INVOICE_INCREMENT_BY', 1),
        'auto_increment' => env('INVOICE_AUTO_INCREMENT', true),
        'allow_manual_override' => env('INVOICE_ALLOW_MANUAL_OVERRIDE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Annual Reset Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic reset of invoice numbering at the start of each year.
    |
    */

    'annual_reset' => [
        'enabled' => env('INVOICE_ANNUAL_RESET', true),
        'reset_date' => env('INVOICE_RESET_DATE', '01-01'), // MM-DD format
        'reset_to_number' => env('INVOICE_RESET_TO_NUMBER', 1),
        'preserve_historical_numbers' => env('INVOICE_PRESERVE_HISTORICAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Duplicate Check Settings
    |--------------------------------------------------------------------------
    |
    | Settings for preventing and handling duplicate invoice numbers.
    |
    */

    'duplicate_check' => [
        'enabled' => env('INVOICE_DUPLICATE_CHECK', true),
        'check_scope' => env('INVOICE_CHECK_SCOPE', 'global'), // 'global', 'year', 'month'
        'on_duplicate' => env('INVOICE_ON_DUPLICATE', 'increment'), // 'increment', 'error', 'skip'
        'max_attempts' => env('INVOICE_MAX_ATTEMPTS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatting and Display Settings
    |--------------------------------------------------------------------------
    |
    | Control how invoice numbers are formatted and displayed.
    |
    */

    'formatting' => [
        'date_format' => env('INVOICE_DATE_FORMAT', 'Y'), // Used when including year/month
        'case' => env('INVOICE_CASE', 'upper'), // 'upper', 'lower', 'mixed'
        'zero_padding' => env('INVOICE_ZERO_PADDING', true),
        'display_separator' => env('INVOICE_DISPLAY_SEPARATOR', '-'),
        'preview_format' => env('INVOICE_PREVIEW_FORMAT', '{prefix}{separator}{year}{separator}{sequence}'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security and Validation Settings
    |--------------------------------------------------------------------------
    |
    | Security measures and validation rules for invoice numbers.
    |
    */

    'security' => [
        'max_length' => env('INVOICE_MAX_LENGTH', 50),
        'allowed_characters' => env('INVOICE_ALLOWED_CHARS', 'A-Z0-9\-_'),
        'require_unique_per_customer' => env('INVOICE_UNIQUE_PER_CUSTOMER', false),
        'encryption_enabled' => env('INVOICE_ENCRYPTION', false),
        'audit_trail' => env('INVOICE_AUDIT_TRAIL', true),
        'validation_rules' => [
            'pattern' => 'required|string|max:' . env('INVOICE_MAX_LENGTH', 50),
            'uniqueness' => 'unique:invoices,invoice_number',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Settings
    |--------------------------------------------------------------------------
    |
    | Additional advanced configuration options.
    |
    */

    'advanced' => [
        'cache_enabled' => env('INVOICE_CACHE_ENABLED', true),
        'cache_ttl' => env('INVOICE_CACHE_TTL', 3600), // seconds
        'batch_generation' => env('INVOICE_BATCH_GENERATION', false),
        'batch_size' => env('INVOICE_BATCH_SIZE', 100),
        'performance_mode' => env('INVOICE_PERFORMANCE_MODE', 'standard'), // 'standard', 'high_performance'
    ],

];