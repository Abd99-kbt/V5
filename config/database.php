<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => env('DB_USE_BUFFERED_QUERY', true),
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))",
                PDO::ATTR_TIMEOUT => env('DB_TIMEOUT', 10),
                PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', false),
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]) : [],
            'modes' => [
                'ONLY_FULL_GROUP_BY',
                'STRICT_TRANS_TABLES',
                'NO_ZERO_IN_DATE',
                'NO_ZERO_DATE',
                'ERROR_FOR_DIVISION_BY_ZERO',
                'NO_AUTO_CREATE_USER',
                'NO_ENGINE_SUBSTITUTION'
            ],
            'engine' => env('DB_ENGINE', 'InnoDB'),
            'isolation_level' => env('DB_ISOLATION_LEVEL', 'READ COMMITTED'),
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSL_MODE', 'prefer'),
            'options' => extension_loaded('pdo_pgsql') ? array_filter([
                PDO::ATTR_TIMEOUT => env('DB_TIMEOUT', 10),
                PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', false),
                PDO::ATTR_EMULATE_PREPARES => false,
            ]) : [],
        ],

        // Production MySQL with connection pooling
        'mysql_cluster' => [
            'driver' => 'mysql',
            'url' => env('DB_CLUSTER_URL'),
            'read' => [
                'host' => env('DB_READ_HOST', '127.0.0.1'),
                'port' => env('DB_READ_PORT', '3306'),
                'database' => env('DB_DATABASE', 'laravel'),
                'username' => env('DB_READ_USERNAME', env('DB_USERNAME')),
                'password' => env('DB_READ_PASSWORD', env('DB_PASSWORD')),
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => env('DB_USE_BUFFERED_QUERY', true),
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                    PDO::ATTR_TIMEOUT => env('DB_TIMEOUT', 3), // Reduced for better performance
                    PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', true),
                ]) : [],
            ],
            'write' => [
                'host' => env('DB_WRITE_HOST', '127.0.0.1'),
                'port' => env('DB_WRITE_PORT', '3306'),
                'database' => env('DB_DATABASE', 'laravel'),
                'username' => env('DB_WRITE_USERNAME', env('DB_USERNAME')),
                'password' => env('DB_WRITE_PASSWORD', env('DB_PASSWORD')),
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => env('DB_USE_BUFFERED_QUERY', true),
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                    PDO::ATTR_TIMEOUT => env('DB_TIMEOUT', 5),
                    PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', true),
                ]) : [],
            ],
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'pool' => [
                'min_connections' => env('DB_POOL_MIN', 10), // Increased for production
                'max_connections' => env('DB_POOL_MAX', 100),
                'acquire_timeout' => env('DB_POOL_TIMEOUT', 30), // Reduced timeout
                'idle_timeout' => env('DB_POOL_IDLE_TIMEOUT', 300), // Reduced for better resource management
                'wait_timeout' => env('DB_POOL_WAIT_TIMEOUT', 3),
                'retry_attempts' => env('DB_POOL_RETRY_ATTEMPTS', 3),
                'retry_delay' => env('DB_POOL_RETRY_DELAY', 100), // ms
            ],
        ],

        // High-performance read replica connection
        'mysql_read_replica' => [
            'driver' => 'mysql',
            'host' => env('DB_REPLICA_HOST', '127.0.0.1'),
            'port' => env('DB_REPLICA_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_REPLICA_USERNAME', env('DB_USERNAME')),
            'password' => env('DB_REPLICA_PASSWORD', env('DB_PASSWORD')),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => env('DB_USE_BUFFERED_QUERY', true),
                PDO::ATTR_TIMEOUT => env('DB_TIMEOUT', 3),
                PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', true),
            ]) : [],
            'pool' => [
                'min_connections' => env('DB_READ_POOL_MIN', 8),
                'max_connections' => env('DB_READ_POOL_MAX', 50),
                'acquire_timeout' => env('DB_READ_POOL_TIMEOUT', 15),
                'idle_timeout' => env('DB_READ_POOL_IDLE_TIMEOUT', 180),
                'wait_timeout' => env('DB_READ_POOL_WAIT_TIMEOUT', 2),
            ],
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        // Session Redis connection
        'session' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_SESSION_DB', '2'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        // Queue Redis connection
        'queue' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_QUEUE_DB', '3'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Database Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Production database performance monitoring and optimization settings.
    |
    */

    'monitoring' => [
        'enabled' => env('DB_MONITORING_ENABLED', true),
        'slow_query_log' => env('DB_SLOW_QUERY_LOG', true),
        'slow_query_threshold' => env('DB_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'connection_pooling' => env('DB_CONNECTION_POOLING', true),
        'query_cache' => env('DB_QUERY_CACHE', true),
        'metrics_retention_days' => env('DB_METRICS_RETENTION', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Eloquent Performance Optimizations
    |--------------------------------------------------------------------------
    |
    | Eloquent ORM performance optimization settings for production.
    |
    */

    'eloquent' => [
        'eager_loading' => env('ELOQUENT_EAGER_LOADING', true),
        'caching' => env('ELOQUENT_CACHING', true),
        'pagination' => [
            'default_limit' => env('ELOQUENT_PAGINATION_LIMIT', 15),
            'max_limit' => env('ELOQUENT_MAX_PAGINATION_LIMIT', 100),
        ],
        'relationships' => [
            'lazy_loading' => env('ELOQUENT_LAZY_LOADING', false), // Disable for better performance
            'eager_loading_threshold' => env('ELOQUENT_EAGER_LOADING_THRESHOLD', 10),
        ],
    ],


];
