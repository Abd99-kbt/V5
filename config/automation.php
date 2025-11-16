<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automation Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the system's automation features
    | including service settings, timing, limits, alerts, IoT integration,
    | reporting, and security settings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Service Settings
    |--------------------------------------------------------------------------
    |
    | Enable or disable various automated services in the system.
    |
    */
    'services' => [
        'automated_pricing' => env('AUTOMATION_PRICING_ENABLED', true),
        'automated_approval' => env('AUTOMATION_APPROVAL_ENABLED', true),
        'ai_prediction' => env('AUTOMATION_AI_PREDICTION_ENABLED', true),
        'iot_integration' => env('AUTOMATION_IOT_ENABLED', true),
        'smart_material_selection' => env('AUTOMATION_SMART_MATERIAL_ENABLED', true),
        'automated_quality_control' => env('AUTOMATION_QUALITY_CONTROL_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timing Settings
    |--------------------------------------------------------------------------
    |
    | Configure the timing intervals for various automated operations.
    |
    */
    'schedules' => [
        'pricing_update_interval' => env('PRICING_UPDATE_INTERVAL', 'hourly'),
        'approval_check_interval' => env('APPROVAL_CHECK_INTERVAL', '15 minutes'),
        'prediction_update_interval' => env('PREDICTION_UPDATE_INTERVAL', 'daily'),
        'iot_sync_interval' => env('IOT_SYNC_INTERVAL', '5 minutes'),
        'quality_check_interval' => env('QUALITY_CHECK_INTERVAL', '30 minutes'),
        'material_selection_interval' => env('MATERIAL_SELECTION_INTERVAL', '2 hours'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits and Criteria
    |--------------------------------------------------------------------------
    |
    | Define limits and criteria for automated operations to ensure
    | safe and controlled automation.
    |
    */
    'limits' => [
        'max_automated_orders_per_hour' => env('MAX_AUTOMATED_ORDERS_PER_HOUR', 100),
        'min_confidence_threshold' => env('MIN_CONFIDENCE_THRESHOLD', 0.8),
        'max_price_change_percentage' => env('MAX_PRICE_CHANGE_PERCENTAGE', 10),
        'approval_threshold' => env('APPROVAL_THRESHOLD', 5000),
        'max_concurrent_automations' => env('MAX_CONCURRENT_AUTOMATIONS', 5),
        'processing_timeout_seconds' => env('PROCESSING_TIMEOUT_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerts and Notifications
    |--------------------------------------------------------------------------
    |
    | Configure alert thresholds and notification channels for
    | automated system monitoring.
    |
    */
    'alerts' => [
        'email_notifications' => env('EMAIL_NOTIFICATIONS_ENABLED', true),
        'sms_notifications' => env('SMS_NOTIFICATIONS_ENABLED', false),
        'slack_notifications' => env('SLACK_NOTIFICATIONS_ENABLED', false),
        'push_notifications' => env('PUSH_NOTIFICATIONS_ENABLED', true),

        'alert_thresholds' => [
            'low_stock' => env('LOW_STOCK_ALERT_THRESHOLD', 10),
            'high_error_rate' => env('HIGH_ERROR_RATE_THRESHOLD', 5),
            'system_performance' => env('SYSTEM_PERFORMANCE_THRESHOLD', 80),
            'automation_failure_rate' => env('AUTOMATION_FAILURE_RATE_THRESHOLD', 3),
        ],

        'notification_recipients' => [
            'admins' => env('ADMIN_NOTIFICATION_EMAILS', 'admin@example.com'),
            'managers' => env('MANAGER_NOTIFICATION_EMAILS', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IoT and Device Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Internet of Things integration and device management.
    |
    */
    'iot' => [
        'enabled' => env('IOT_ENABLED', true),

        'devices' => [
            'sensors' => env('IOT_SENSORS_ENABLED', true),
            'actuators' => env('IOT_ACTUATORS_ENABLED', true),
            'cameras' => env('IOT_CAMERAS_ENABLED', false),
            'scanners' => env('IOT_SCANNERS_ENABLED', true),
        ],

        'protocols' => [
            'mqtt' => [
                'enabled' => env('MQTT_ENABLED', true),
                'broker' => env('MQTT_BROKER', 'localhost'),
                'port' => env('MQTT_PORT', 1883),
                'username' => env('MQTT_USERNAME'),
                'password' => env('MQTT_PASSWORD'),
            ],
            'coap' => [
                'enabled' => env('COAP_ENABLED', false),
                'host' => env('COAP_HOST', 'localhost'),
                'port' => env('COAP_PORT', 5683),
            ],
            'http' => [
                'enabled' => env('HTTP_IOT_ENABLED', true),
                'timeout' => env('HTTP_IOT_TIMEOUT', 30),
            ],
        ],

        'security' => [
            'encryption' => env('IOT_ENCRYPTION_ENABLED', true),
            'authentication' => env('IOT_AUTHENTICATION_REQUIRED', true),
            'certificate_validation' => env('IOT_CERTIFICATE_VALIDATION', true),
            'device_whitelist' => env('IOT_DEVICE_WHITELIST_ENABLED', true),
        ],

        'data_collection' => [
            'interval_seconds' => env('IOT_DATA_COLLECTION_INTERVAL', 60),
            'retention_days' => env('IOT_DATA_RETENTION_DAYS', 30),
            'batch_size' => env('IOT_DATA_BATCH_SIZE', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports and Statistics
    |--------------------------------------------------------------------------
    |
    | Settings for automated report generation and statistical analysis.
    |
    */
    'reports' => [
        'auto_generate' => env('AUTO_GENERATE_REPORTS', true),
        'report_interval' => env('REPORT_INTERVAL', 'daily'),
        'statistics_retention_days' => env('STATISTICS_RETENTION_DAYS', 90),

        'report_types' => [
            'performance' => env('PERFORMANCE_REPORTS_ENABLED', true),
            'automation' => env('AUTOMATION_REPORTS_ENABLED', true),
            'inventory' => env('INVENTORY_REPORTS_ENABLED', true),
            'financial' => env('FINANCIAL_REPORTS_ENABLED', true),
        ],

        'export_formats' => ['pdf', 'csv', 'excel', 'json'],
        'email_reports' => env('EMAIL_REPORTS_ENABLED', true),
        'report_recipients' => env('REPORT_RECIPIENTS', 'reports@example.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Limits and Verification
    |--------------------------------------------------------------------------
    |
    | Security settings for automated operations including access control,
    | verification requirements, and audit logging.
    |
    */
    'security' => [
        'max_failed_attempts' => env('MAX_FAILED_ATTEMPTS', 3),
        'lockout_duration_minutes' => env('LOCKOUT_DURATION_MINUTES', 30),
        'require_2fa' => env('REQUIRE_2FA', false),
        'audit_log_retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 365),

        'verification' => [
            'require_manual_override' => env('REQUIRE_MANUAL_OVERRIDE', false),
            'approval_required_above_threshold' => env('APPROVAL_REQUIRED_ABOVE_THRESHOLD', 10000),
            'dual_authorization' => env('DUAL_AUTHORIZATION_ENABLED', false),
        ],

        'access_control' => [
            'role_based_access' => env('ROLE_BASED_ACCESS_ENABLED', true),
            'ip_whitelist' => env('IP_WHITELIST_ENABLED', false),
            'time_based_restrictions' => env('TIME_BASED_RESTRICTIONS_ENABLED', false),
        ],

        'encryption' => [
            'data_at_rest' => env('DATA_ENCRYPTION_AT_REST', true),
            'data_in_transit' => env('DATA_ENCRYPTION_IN_TRANSIT', true),
            'algorithm' => env('ENCRYPTION_ALGORITHM', 'AES-256-GCM'),
        ],
    ],

];