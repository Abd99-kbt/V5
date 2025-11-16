<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "octane", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
            'options' => [
                // Performance optimizations for Redis
                'timeout' => env('REDIS_TIMEOUT', 5),
                'persistent' => env('REDIS_PERSISTENT', true),
                'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),
                'retry_interval' => env('REDIS_RETRY_INTERVAL', 0),
                'read_timeout' => env('REDIS_READ_TIMEOUT', 0.0),
                'context' => env('REDIS_CONTEXT'),
                'stream' => env('REDIS_STREAM'),
            ],
        ],

        // Production-optimized Redis stores
        'redis-high-performance' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'cache'),
            'options' => [
                'timeout' => env('REDIS_TIMEOUT', 5),
                'persistent' => true,
                'prefix' => 'hp:',
                'retry_interval' => 0,
                'read_timeout' => 0.0,
            ],
        ],

        'redis-session' => [
            'driver' => 'redis',
            'connection' => 'session',
            'lock_connection' => 'session',
            'options' => [
                'timeout' => env('REDIS_SESSION_TIMEOUT', 30),
                'persistent' => true,
                'prefix' => 'session:',
                'retry_interval' => 0,
                'read_timeout' => 0.0,
            ],
        ],

        'redis-queue' => [
            'driver' => 'redis',
            'connection' => 'queue',
            'lock_connection' => 'queue',
            'options' => [
                'timeout' => env('REDIS_QUEUE_TIMEOUT', 60),
                'persistent' => true,
                'prefix' => 'queue:',
                'retry_interval' => 0,
                'read_timeout' => 0.0,
            ],
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

    /*
    |--------------------------------------------------------------------------
    | Cache Warming and Preloading Strategies
    |--------------------------------------------------------------------------
    |
    | Define cache warming strategies to pre-populate frequently accessed data
    | and improve response times in production environments.
    |
    */

    'warming' => [
        'enabled' => env('CACHE_WARMING_ENABLED', true),
        'interval' => env('CACHE_WARMING_INTERVAL', 3600), // 1 hour
        'items' => [
            'user_permissions' => env('CACHE_USER_PERMISSIONS', true),
            'app_config' => env('CACHE_APP_CONFIG', true),
            'menu_items' => env('CACHE_MENU_ITEMS', true),
            'system_settings' => env('CACHE_SYSTEM_SETTINGS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Invalidation Policies
    |--------------------------------------------------------------------------
    |
    | Define intelligent cache invalidation policies based on data changes
    | and business logic requirements.
    |
    */

    'invalidation' => [
        'tags' => env('CACHE_INVALIDATION_TAGS', true),
        'pattern_based' => env('CACHE_INVALIDATION_PATTERN', true),
        'ttl_variance' => env('CACHE_INVALIDATION_TTL_VARIANCE', 0.1), // 10% randomization
        'stale_while_revalidate' => env('CACHE_STALE_WHILE_REVALIDATE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Metrics and Monitoring
    |--------------------------------------------------------------------------
    |
    | Cache performance metrics and monitoring configuration.
    |
    */

    'metrics' => [
        'enabled' => env('CACHE_METRICS_ENABLED', true),
        'store' => env('CACHE_METRICS_STORE', 'redis'),
        'prefix' => 'metrics:cache:',
        'retention_days' => env('CACHE_METRICS_RETENTION', 30),
    ],


];
