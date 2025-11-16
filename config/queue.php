<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "failover", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'queue'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        // High performance Redis queues
        'redis-high-priority' => [
            'driver' => 'redis',
            'connection' => 'queue',
            'queue' => env('REDIS_QUEUE_HIGH', 'high-priority'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 60), // Faster retry for high priority
            'block_for' => null,
            'after_commit' => false,
        ],

        'redis-low-priority' => [
            'driver' => 'redis',
            'connection' => 'queue',
            'queue' => env('REDIS_QUEUE_LOW', 'low-priority'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 180), // Slower retry for low priority
            'block_for' => null,
            'after_commit' => false,
        ],

        'redis-batch' => [
            'driver' => 'redis',
            'connection' => 'queue',
            'queue' => env('REDIS_QUEUE_BATCH', 'batch'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 300), // Longer retry for batch jobs
            'block_for' => null,
            'after_commit' => false,
        ],

        // Monitoring and performance queues
        'redis-monitoring' => [
            'driver' => 'redis',
            'connection' => 'queue',
            'queue' => env('REDIS_QUEUE_MONITORING', 'monitoring'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 120),
            'block_for' => null,
            'after_commit' => false,
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'sync',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Production-optimized queue worker settings for maximum performance
    | and resource efficiency.
    |
    */

    'workers' => [
        'memory_limit' => env('QUEUE_WORKER_MEMORY_LIMIT', 256), // MB
        'timeout' => env('QUEUE_WORKER_TIMEOUT', 3600), // seconds
        'sleep' => env('QUEUE_WORKER_SLEEP', 3), // seconds
        'tries' => env('QUEUE_WORKER_MAX_TRIES', 3),
        'force' => env('QUEUE_WORKER_FORCE', false),
        'stop_when_empty' => env('QUEUE_WORKER_STOP_WHEN_EMPTY', false),
        'max_jobs' => env('QUEUE_WORKER_MAX_JOBS', 1000), // Process max 1000 jobs then restart
        'max_time' => env('QUEUE_WORKER_MAX_TIME', 3600), // Run for max 1 hour then restart
        'rest' => env('QUEUE_WORKER_REST', 10), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Monitoring and Metrics
    |--------------------------------------------------------------------------
    |
    | Queue performance monitoring and metrics collection.
    |
    */

    'monitoring' => [
        'enabled' => env('QUEUE_MONITORING_ENABLED', true),
        'store_metrics' => env('QUEUE_STORE_METRICS', true),
        'metrics_store' => env('QUEUE_METRICS_STORE', 'redis'),
        'retention_days' => env('QUEUE_METRICS_RETENTION', 30),
        'alerts' => [
            'failed_jobs_threshold' => env('QUEUE_FAILED_JOBS_ALERT', 100),
            'queue_length_threshold' => env('QUEUE_LENGTH_ALERT', 1000),
            'processing_time_threshold' => env('QUEUE_PROCESSING_TIME_ALERT', 300), // seconds
        ],
    ],


];
