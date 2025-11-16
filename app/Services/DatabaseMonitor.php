<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class DatabaseMonitor
{
    /**
     * Database connection name to monitor
     */
    protected string $connection;

    /**
     * Cache key prefix for monitoring data
     */
    protected string $cachePrefix = 'db_monitor_';

    /**
     * Alert thresholds
     */
    protected array $thresholds = [
        'query_time' => 1000, // milliseconds
        'connection_usage' => 80, // percentage
        'slow_query_count' => 100, // per hour
        'table_size_growth' => 10, // percentage per day
        'lock_wait_time' => 5000, // milliseconds
        'error_rate' => 0.1, // percentage
    ];

    public function __construct(string $connection = 'mysql')
    {
        $this->connection = $connection;
    }

    /**
     * Get overall database health status
     */
    public function getHealthStatus(): array
    {
        $status = [
            'overall' => 'healthy',
            'timestamp' => now()->toISOString(),
            'connection' => $this->connection,
            'checks' => []
        ];

        try {
            // Connection check
            $status['checks']['connection'] = $this->checkConnection();
            
            // Performance check
            $status['checks']['performance'] = $this->checkPerformance();
            
            // Storage check
            $status['checks']['storage'] = $this->checkStorage();
            
            // Query statistics check
            $status['checks']['query_stats'] = $this->checkQueryStatistics();
            
            // Index usage check
            $status['checks']['indexes'] = $this->checkIndexUsage();
            
            // Table size check
            $status['checks']['table_sizes'] = $this->checkTableSizes();
            
            // Lock and deadlock check
            $status['checks']['locks'] = $this->checkLocks();
            
            // Error rate check
            $status['checks']['errors'] = $this->checkErrorRate();

            // Determine overall status
            $criticalIssues = collect($status['checks'])->filter(fn($check) => $check['status'] === 'critical')->count();
            $warningIssues = collect($status['checks'])->filter(fn($check) => $check['status'] === 'warning')->count();
            
            if ($criticalIssues > 0) {
                $status['overall'] = 'critical';
            } elseif ($warningIssues > 0) {
                $status['overall'] = 'warning';
            }

            // Cache the health status
            $this->cacheHealthStatus($status);

        } catch (\Exception $e) {
            Log::error('Database health check failed', [
                'connection' => $this->connection,
                'error' => $e->getMessage()
            ]);

            $status['overall'] = 'error';
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    /**
     * Check database connection
     */
    protected function checkConnection(): array
    {
        $start = microtime(true);
        
        try {
            DB::connection($this->connection)->getPdo();
            $responseTime = (microtime(true) - $start) * 1000; // Convert to milliseconds
            
            return [
                'status' => $responseTime < 100 ? 'healthy' : 'warning',
                'response_time_ms' => round($responseTime, 2),
                'message' => 'Connection successful',
                'threshold' => 100
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'response_time_ms' => null,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check database performance metrics
     */
    protected function checkPerformance(): array
    {
        try {
            if ($this->isMySQL()) {
                return $this->checkMySQLPerformance();
            } elseif ($this->isPostgreSQL()) {
                return $this->checkPostgreSQLPerformance();
            }
            
            return [
                'status' => 'warning',
                'message' => 'Unsupported database type for performance monitoring'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Performance check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check MySQL-specific performance metrics
     */
    protected function checkMySQLPerformance(): array
    {
        $stats = DB::connection($this->connection)->select("
            SHOW GLOBAL STATUS WHERE Variable_name IN (
                'Threads_connected', 'Max_used_connections', 'Connections',
                'Slow_queries', 'Questions', 'Innodb_buffer_pool_read_requests',
                'Innodb_buffer_pool_reads', 'Table_locks_immediate'
            )
        ");

        $statusMap = [];
        foreach ($stats as $stat) {
            $statusMap[$stat->Variable_name] = $stat->Value;
        }

        $maxConnections = DB::connection($this->connection)->select("SHOW VARIABLES LIKE 'max_connections'")[0]->Value;
        $connectionUsage = ($statusMap['Threads_connected'] / $maxConnections) * 100;
        $slowQueryRate = $statusMap['Slow_queries'];
        
        $issues = [];
        $status = 'healthy';

        if ($connectionUsage > $this->thresholds['connection_usage']) {
            $status = 'critical';
            $issues[] = "High connection usage: {$connectionUsage}%";
        } elseif ($connectionUsage > $this->thresholds['connection_usage'] * 0.7) {
            $status = 'warning';
            $issues[] = "Elevated connection usage: {$connectionUsage}%";
        }

        if ($slowQueryRate > $this->thresholds['slow_query_count']) {
            $status = 'critical';
            $issues[] = "High slow query count: {$slowQueryRate}";
        }

        return [
            'status' => $status,
            'metrics' => [
                'connection_usage_percent' => round($connectionUsage, 2),
                'active_connections' => $statusMap['Threads_connected'],
                'max_connections' => $maxConnections,
                'total_connections' => $statusMap['Connections'],
                'slow_queries' => $slowQueryRate,
                'total_queries' => $statusMap['Questions'],
                'buffer_pool_hit_rate' => $this->calculateBufferPoolHitRate($statusMap)
            ],
            'issues' => $issues,
            'message' => empty($issues) ? 'Performance metrics within normal range' : implode(', ', $issues)
        ];
    }

    /**
     * Check PostgreSQL-specific performance metrics
     */
    protected function checkPostgreSQLPerformance(): array
    {
        $stats = DB::connection($this->connection)->select("
            SELECT 
                numbackends,
                xact_commit,
                xact_rollback,
                blks_read,
                blks_hit,
                tup_returned,
                tup_fetched,
                tup_inserted,
                tup_updated,
                tup_deleted,
                conflicts,
                temp_files,
                temp_bytes,
                deadlocks,
                blk_read_time,
                blk_write_time
            FROM pg_stat_database 
            WHERE datname = current_database()
        ")[0];

        $maxConnections = DB::connection($this->connection)->select("SHOW max_connections")[0]->max_connections;
        $connectionUsage = ($stats->numbackends / $maxConnections) * 100;
        
        $issues = [];
        $status = 'healthy';

        if ($connectionUsage > $this->thresholds['connection_usage']) {
            $status = 'critical';
            $issues[] = "High connection usage: {$connectionUsage}%";
        } elseif ($connectionUsage > $this->thresholds['connection_usage'] * 0.7) {
            $status = 'warning';
            $issues[] = "Elevated connection usage: {$connectionUsage}%";
        }

        if ($stats->deadlocks > 0) {
            $status = 'warning';
            $issues[] = "Deadlocks detected: {$stats->deadlocks}";
        }

        $hitRate = $stats->blks_hit > 0 ? ($stats->blks_hit / ($stats->blks_hit + $stats->blks_read)) * 100 : 0;
        if ($hitRate < 95) {
            $status = 'warning';
            $issues[] = "Low cache hit rate: " . round($hitRate, 2) . "%";
        }

        return [
            'status' => $status,
            'metrics' => [
                'connection_usage_percent' => round($connectionUsage, 2),
                'active_connections' => $stats->numbackends,
                'max_connections' => $maxConnections,
                'cache_hit_rate' => round($hitRate, 2),
                'total_commits' => $stats->xact_commit,
                'total_rollbacks' => $stats->xact_rollback,
                'deadlocks' => $stats->deadlocks,
                'temp_files' => $stats->temp_files
            ],
            'issues' => $issues,
            'message' => empty($issues) ? 'Performance metrics within normal range' : implode(', ', $issues)
        ];
    }

    /**
     * Check storage metrics
     */
    protected function checkStorage(): array
    {
        try {
            if ($this->isMySQL()) {
                return $this->checkMySQLStorage();
            } elseif ($this->isPostgreSQL()) {
                return $this->checkPostgreSQLStorage();
            }
            
            return ['status' => 'warning', 'message' => 'Unsupported database type for storage monitoring'];
        } catch (\Exception $e) {
            return ['status' => 'critical', 'message' => 'Storage check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check MySQL storage metrics
     */
    protected function checkMySQLStorage(): array
    {
        $databases = DB::connection($this->connection)->select("
            SELECT 
                table_schema as database_name,
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) as size_mb
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            GROUP BY table_schema
        ");

        $totalSize = $databases[0]->size_mb ?? 0;
        
        return [
            'status' => 'healthy',
            'metrics' => [
                'database_size_mb' => $totalSize,
                'database_size_gb' => round($totalSize / 1024, 2)
            ],
            'message' => "Database size: {$totalSize} MB"
        ];
    }

    /**
     * Check PostgreSQL storage metrics
     */
    protected function checkPostgreSQLStorage(): array
    {
        $size = DB::connection($this->connection)->select("
            SELECT pg_size_pretty(pg_database_size(current_database())) as size
        ")[0]->size;

        $sizeBytes = DB::connection($this->connection)->select("
            SELECT pg_database_size(current_database()) as size_bytes
        ")[0]->size_bytes;

        return [
            'status' => 'healthy',
            'metrics' => [
                'database_size' => $size,
                'database_size_bytes' => $sizeBytes
            ],
            'message' => "Database size: {$size}"
        ];
    }

    /**
     * Check query statistics
     */
    protected function checkQueryStatistics(): array
    {
        try {
            if ($this->isMySQL()) {
                return $this->checkMySQLQueryStats();
            } elseif ($this->isPostgreSQL()) {
                return $this->checkPostgreSQLQueryStats();
            }
            
            return ['status' => 'warning', 'message' => 'Unsupported database type for query statistics'];
        } catch (\Exception $e) {
            return ['status' => 'critical', 'message' => 'Query statistics check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check MySQL query statistics
     */
    protected function checkMySQLQueryStats(): array
    {
        try {
            DB::connection($this->connection)->select("SET GLOBAL log_queries_not_using_indexes = ON");
            
            return [
                'status' => 'healthy',
                'metrics' => [
                    'slow_query_log_enabled' => true,
                    'query_cache_enabled' => $this->isQueryCacheEnabled()
                ],
                'message' => 'Query monitoring configured'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Query statistics monitoring unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check PostgreSQL query statistics
     */
    protected function checkPostgreSQLQueryStats(): array
    {
        try {
            // Enable pg_stat_statements extension if not already enabled
            DB::connection($this->connection)->statement("CREATE EXTENSION IF NOT EXISTS pg_stat_statements");
            
            $topQueries = DB::connection($this->connection)->select("
                SELECT 
                    query,
                    calls,
                    total_time,
                    mean_time,
                    rows
                FROM pg_stat_statements
                ORDER BY total_time DESC
                LIMIT 5
            ");

            return [
                'status' => 'healthy',
                'metrics' => [
                    'pg_stat_statements_enabled' => true,
                    'top_queries_count' => count($topQueries)
                ],
                'top_queries' => $topQueries,
                'message' => 'Query statistics available'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Query statistics unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check index usage statistics
     */
    protected function checkIndexUsage(): array
    {
        try {
            if ($this->isMySQL()) {
                return $this->checkMySQLIndexUsage();
            } elseif ($this->isPostgreSQL()) {
                return $this->checkPostgreSQLIndexUsage();
            }
            
            return ['status' => 'warning', 'message' => 'Unsupported database type for index monitoring'];
        } catch (\Exception $e) {
            return ['status' => 'critical', 'message' => 'Index usage check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check MySQL index usage
     */
    protected function checkMySQLIndexUsage(): array
    {
        $indexUsage = DB::connection($this->connection)->select("
            SELECT 
                TABLE_NAME,
                INDEX_NAME,
                NON_UNIQUE,
                CARDINALITY,
                SUB_PART,
                PACKED,
                NULLABLE,
                INDEX_TYPE
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            ORDER BY TABLE_NAME, INDEX_NAME
        ");

        $unusedIndexes = [];
        foreach ($indexUsage as $index) {
            if ($index->NON_UNIQUE == 0 && $index->CARDINALITY == 1) {
                $unusedIndexes[] = "{$index->TABLE_NAME}.{$index->INDEX_NAME}";
            }
        }

        return [
            'status' => empty($unusedIndexes) ? 'healthy' : 'warning',
            'metrics' => [
                'total_indexes' => count($indexUsage),
                'potential_unused_indexes' => count($unusedIndexes)
            ],
            'unused_indexes' => $unusedIndexes,
            'message' => empty($unusedIndexes) ? 'Index usage appears optimal' : 'Potential unused indexes detected'
        ];
    }

    /**
     * Check PostgreSQL index usage
     */
    protected function checkPostgreSQLIndexUsage(): array
    {
        $indexStats = DB::connection($this->connection)->select("
            SELECT 
                schemaname,
                tablename,
                indexname,
                idx_scan,
                idx_tup_read,
                idx_tup_fetch
            FROM pg_stat_user_indexes
            ORDER BY idx_scan ASC
        ");

        $unusedIndexes = [];
        foreach ($indexStats as $stat) {
            if ($stat->idx_scan == 0) {
                $unusedIndexes[] = "{$stat->tablename}.{$stat->indexname}";
            }
        }

        return [
            'status' => empty($unusedIndexes) ? 'healthy' : 'warning',
            'metrics' => [
                'total_indexes' => count($indexStats),
                'unused_indexes' => count($unusedIndexes)
            ],
            'unused_indexes' => $unusedIndexes,
            'message' => empty($unusedIndexes) ? 'Index usage appears optimal' : 'Unused indexes detected'
        ];
    }

    /**
     * Check table sizes and growth
     */
    protected function checkTableSizes(): array
    {
        try {
            if ($this->isMySQL()) {
                return $this->checkMySQLTableSizes();
            } elseif ($this->isPostgreSQL()) {
                return $this->checkPostgreSQLTableSizes();
            }
            
            return ['status' => 'warning', 'message' => 'Unsupported database type for table size monitoring'];
        } catch (\Exception $e) {
            return ['status' => 'critical', 'message' => 'Table size check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check MySQL table sizes
     */
    protected function checkMySQLTableSizes(): array
    {
        $tables = DB::connection($this->connection)->select("
            SELECT 
                table_name,
                table_rows,
                ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb,
                ROUND(data_free / 1024 / 1024, 2) as free_space_mb
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            ORDER BY (data_length + index_length) DESC
        ");

        $largeTables = [];
        foreach ($tables as $table) {
            if ($table->size_mb > 1000) { // Tables larger than 1GB
                $largeTables[] = [
                    'name' => $table->table_name,
                    'size_mb' => $table->size_mb,
                    'rows' => $table->table_rows,
                    'free_space_mb' => $table->free_space_mb
                ];
            }
        }

        return [
            'status' => empty($largeTables) ? 'healthy' : 'warning',
            'metrics' => [
                'total_tables' => count($tables),
                'large_tables_count' => count($largeTables)
            ],
            'large_tables' => $largeTables,
            'message' => empty($largeTables) ? 'Table sizes within normal range' : 'Large tables detected'
        ];
    }

    /**
     * Check PostgreSQL table sizes
     */
    protected function checkPostgreSQLTableSizes(): array
    {
        $tables = DB::connection($this->connection)->select("
            SELECT 
                schemaname,
                tablename,
                n_tup_ins,
                n_tup_upd,
                n_tup_del,
                n_live_tup,
                n_dead_tup,
                pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size,
                pg_total_relation_size(schemaname||'.'||tablename) as size_bytes
            FROM pg_stat_user_tables
            ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
        ");

        $largeTables = [];
        foreach ($tables as $table) {
            if ($table->size_bytes > 1073741824) { // Tables larger than 1GB
                $largeTables[] = [
                    'name' => "{$table->schemaname}.{$table->tablename}",
                    'size' => $table->size,
                    'size_bytes' => $table->size_bytes,
                    'live_tuples' => $table->n_live_tup,
                    'dead_tuples' => $table->n_dead_tup
                ];
            }
        }

        return [
            'status' => empty($largeTables) ? 'healthy' : 'warning',
            'metrics' => [
                'total_tables' => count($tables),
                'large_tables_count' => count($largeTables)
            ],
            'large_tables' => $largeTables,
            'message' => empty($largeTables) ? 'Table sizes within normal range' : 'Large tables detected'
        ];
    }

    /**
     * Check locks and deadlocks
     */
    protected function checkLocks(): array
    {
        try {
            if ($this->isMySQL()) {
                return $this->checkMySQLLocks();
            } elseif ($this->isPostgreSQL()) {
                return $this->checkPostgreSQLLocks();
            }
            
            return ['status' => 'warning', 'message' => 'Unsupported database type for lock monitoring'];
        } catch (\Exception $e) {
            return ['status' => 'critical', 'message' => 'Lock check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check MySQL locks
     */
    protected function checkMySQLLocks(): array
    {
        $processlist = DB::connection($this->connection)->select("SHOW PROCESSLIST");
        $lockedProcesses = collect($processlist)->filter(fn($p) => $p->State === 'Locked' || $p->State === 'Waiting for table metadata lock');
        
        return [
            'status' => $lockedProcesses->count() > 0 ? 'warning' : 'healthy',
            'metrics' => [
                'total_processes' => count($processlist),
                'locked_processes' => $lockedProcesses->count()
            ],
            'locked_processes' => $lockedProcesses->values()->all(),
            'message' => $lockedProcesses->count() > 0 ? 'Locked processes detected' : 'No locks detected'
        ];
    }

    /**
     * Check PostgreSQL locks
     */
    protected function checkPostgreSQLLocks(): array
    {
        $locks = DB::connection($this->connection)->select("
            SELECT 
                pid,
                usename,
                datname,
                query_start,
                state,
                query
            FROM pg_stat_activity
            WHERE state = 'active' AND pid <> pg_backend_pid()
        ");

        $blockedQueries = DB::connection($this->connection)->select("
            SELECT 
                blocked_locks.pid AS blocked_pid,
                blocked_activity.usename AS blocked_user,
                blocking_locks.pid AS blocking_pid,
                blocking_activity.usename AS blocking_user,
                blocked_activity.query AS blocked_statement,
                blocking_activity.query AS blocking_statement
            FROM pg_catalog.pg_locks blocked_locks
            JOIN pg_catalog.pg_stat_activity blocked_activity ON blocked_activity.pid = blocked_locks.pid
            JOIN pg_catalog.pg_locks blocking_locks ON blocking_locks.locktype = blocked_locks.locktype
            JOIN pg_catalog.pg_stat_activity blocking_activity ON blocking_activity.pid = blocking_locks.pid
            WHERE NOT blocked_locks.granted
        ");

        return [
            'status' => empty($blockedQueries) ? 'healthy' : 'warning',
            'metrics' => [
                'active_connections' => count($locks),
                'blocked_queries' => count($blockedQueries)
            ],
            'blocked_queries' => $blockedQueries,
            'message' => empty($blockedQueries) ? 'No blocked queries' : 'Blocked queries detected'
        ];
    }

    /**
     * Check error rates
     */
    protected function checkErrorRate(): array
    {
        try {
            // Get recent errors from database log
            $recentErrors = $this->getRecentErrors();
            
            $errorRate = $this->calculateErrorRate($recentErrors);
            
            return [
                'status' => $errorRate > $this->thresholds['error_rate'] ? 'critical' : 'healthy',
                'metrics' => [
                    'recent_errors' => count($recentErrors),
                    'error_rate_percent' => round($errorRate * 100, 2),
                    'threshold_percent' => $this->thresholds['error_rate'] * 100
                ],
                'recent_errors_list' => $recentErrors,
                'message' => "Error rate: " . round($errorRate * 100, 2) . "%"
            ];
        } catch (\Exception $e) {
            return ['status' => 'critical', 'message' => 'Error rate check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get recent database errors
     */
    protected function getRecentErrors(): array
    {
        // This would typically read from database logs
        // For now, we'll return empty array as it requires log file access
        return [];
    }

    /**
     * Calculate error rate from recent errors
     */
    protected function calculateErrorRate(array $errors): float
    {
        if (empty($errors)) {
            return 0;
        }
        
        $totalQueries = DB::connection($this->connection)->select("
            SHOW GLOBAL STATUS WHERE Variable_name = 'Questions'
        ")[0]->Value;
        
        return count($errors) / max($totalQueries, 1);
    }

    /**
     * Cache health status for performance
     */
    protected function cacheHealthStatus(array $status): void
    {
        $cacheKey = $this->cachePrefix . 'health_' . $this->connection;
        Cache::put($cacheKey, $status, now()->addMinutes(5));
    }

    /**
     * Get cached health status
     */
    public function getCachedHealthStatus(): ?array
    {
        $cacheKey = $this->cachePrefix . 'health_' . $this->connection;
        return Cache::get($cacheKey);
    }

    /**
     * Generate performance report
     */
    public function generatePerformanceReport(): array
    {
        return [
            'generated_at' => now()->toISOString(),
            'database_connection' => $this->connection,
            'health_status' => $this->getHealthStatus(),
            'recommendations' => $this->generateRecommendations()
        ];
    }

    /**
     * Generate optimization recommendations
     */
    protected function generateRecommendations(): array
    {
        $recommendations = [];
        $healthStatus = $this->getHealthStatus();
        
        // Performance recommendations
        if (isset($healthStatus['checks']['performance'])) {
            $perf = $healthStatus['checks']['performance'];
            if ($perf['status'] === 'warning' || $perf['status'] === 'critical') {
                $recommendations[] = 'Consider optimizing slow queries and connection pooling settings';
            }
        }
        
        // Index recommendations
        if (isset($healthStatus['checks']['indexes'])) {
            $indexes = $healthStatus['checks']['indexes'];
            if (!empty($indexes['unused_indexes'])) {
                $recommendations[] = 'Consider removing unused indexes to improve write performance';
            }
        }
        
        // Storage recommendations
        if (isset($healthStatus['checks']['storage'])) {
            $storage = $healthStatus['checks']['storage'];
            if ($storage['metrics']['database_size_gb'] ?? 0 > 10) {
                $recommendations[] = 'Consider implementing data archival for large databases';
            }
        }
        
        return $recommendations;
    }

    /**
     * Send alert notification
     */
    public function sendAlert(string $type, string $message, array $context = []): void
    {
        Log::channel('database_alerts')->alert("Database Alert: {$type}", [
            'message' => $message,
            'connection' => $this->connection,
            'context' => $context,
            'timestamp' => now()->toISOString()
        ]);

        // Send email alert if configured
        if (config('database.alert_email')) {
            try {
                Mail::raw("Database Alert: {$type}\n\n{$message}\n\nContext: " . json_encode($context, JSON_PRETTY_PRINT), function($mail) {
                    $mail->to(config('database.alert_email'))
                         ->subject("Database Alert - {$this->connection}");
                });
            } catch (\Exception $e) {
                Log::error('Failed to send database alert email', ['error' => $e->getMessage()]);
            }
        }

        // Send webhook alert if configured
        if (config('database.alert_webhook')) {
            try {
                Http::post(config('database.alert_webhook'), [
                    'type' => $type,
                    'message' => $message,
                    'connection' => $this->connection,
                    'timestamp' => now()->toISOString(),
                    'context' => $context
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send database alert webhook', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Check if current connection is MySQL
     */
    protected function isMySQL(): bool
    {
        return in_array($this->connection, ['mysql', 'mariadb']);
    }

    /**
     * Check if current connection is PostgreSQL
     */
    protected function isPostgreSQL(): bool
    {
        return in_array($this->connection, ['pgsql', 'postgres', 'postgresql']);
    }

    /**
     * Calculate MySQL buffer pool hit rate
     */
    protected function calculateBufferPoolHitRate(array $stats): float
    {
        if ($stats['Innodb_buffer_pool_read_requests'] > 0) {
            $hitRate = ($stats['Innodb_buffer_pool_reads'] / $stats['Innodb_buffer_pool_read_requests']) * 100;
            return round(100 - $hitRate, 2);
        }
        return 100;
    }

    /**
     * Check if MySQL query cache is enabled
     */
    protected function isQueryCacheEnabled(): bool
    {
        try {
            $queryCache = DB::connection($this->connection)->select("SHOW VARIABLES LIKE 'query_cache_type'");
            return ($queryCache[0]->Value ?? 'OFF') !== 'OFF';
        } catch (\Exception $e) {
            return false;
        }
    }
}