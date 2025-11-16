# Production Database Setup Guide

## Overview
This document provides a comprehensive guide for setting up and managing a production-grade database system optimized for high-traffic applications. The setup supports both MySQL and PostgreSQL with advanced features for monitoring, backup, performance optimization, and security.

## Table of Contents
1. [Database Configuration](#database-configuration)
2. [Migration System](#migration-system)
3. [Backup & Restore](#backup--restore)
4. [Performance Optimization](#performance-optimization)
5. [Monitoring System](#monitoring-system)
6. [Production Commands](#production-commands)
7. [Security Features](#security-features)
8. [Maintenance Procedures](#maintenance-procedures)
9. [Troubleshooting](#troubleshooting)

## Database Configuration

### Supported Databases
- **MySQL 8.0+** (Primary recommendation)
- **PostgreSQL 12+** (Alternative)
- **MariaDB 10.4+** (Compatible)

### Environment Configuration

#### Production Environment Variables (.env.production)
```bash
# Database Connection
DB_CONNECTION=mysql_cluster
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=production_db
DB_USERNAME=production_user
DB_PASSWORD=secure_password

# Connection Pooling
DB_PERSISTENT=true
DB_TIMEOUT=30
DB_POOL_MIN=10
DB_POOL_MAX=100
DB_POOL_TIMEOUT=60
DB_POOL_IDLE_TIMEOUT=600

# MySQL Cluster Configuration
DB_CLUSTER_URL=mysql://user:pass@cluster-host:3306/db
DB_READ_HOST=read-replica-host
DB_READ_PORT=3306
DB_WRITE_HOST=master-host
DB_WRITE_PORT=3306

# Backup Configuration
BACKUP_WEBHOOK_URL=https://your-webhook.com/alerts
BACKUP_EMAIL=admin@company.com
```

#### Connection Pooling Setup
The system supports advanced connection pooling with the following configurations:

```php
'mysql_cluster' => [
    'driver' => 'mysql',
    'read' => [
        'host' => env('DB_READ_HOST'),
        'port' => env('DB_READ_PORT'),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_READ_USERNAME'),
        'password' => env('DB_READ_PASSWORD'),
        'options' => [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_PERSISTENT => true,
        ],
    ],
    'write' => [
        'host' => env('DB_WRITE_HOST'),
        'port' => env('DB_WRITE_PORT'),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_WRITE_USERNAME'),
        'password' => env('DB_WRITE_PASSWORD'),
        'options' => [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_PERSISTENT => true,
        ],
    ],
    'pool' => [
        'min_connections' => 10,
        'max_connections' => 100,
        'acquire_timeout' => 60,
        'idle_timeout' => 600,
    ],
]
```

## Migration System

### Production Migration Command

#### Basic Usage
```bash
# Standard production migration
php artisan db:migrate:production

# Step-by-step migration with confirmations
php artisan db:migrate:production --step

# Create backup before migration
php artisan db:migrate:production --backup

# Dry run - show what would be migrated
php artisan db:migrate:production --dry-run

# Run with verification
php artisan db:migrate:production --verify

# Force without confirmations
php artisan db:migrate:production --force
```

#### Advanced Options
```bash
# Run specific migration batch
php artisan db:migrate:production --batch=2025_11_06

# Custom timeout (1 hour)
php artisan db:migrate:production --timeout=3600

# Use specific database connection
php artisan db:migrate:production --connection=mysql_cluster
```

### Migration Features
- **Pre-flight Checks**: Verify environment, connections, and disk space
- **Automatic Backup**: Create backup before migration if requested
- **Step-by-step Execution**: Optional confirmation for each migration
- **Integrity Verification**: Verify migration success after execution
- **Rollback Support**: Automatic rollback on failure with confirmation
- **Statistics Tracking**: Detailed migration statistics and logging

### Migration Files
- `2025_11_06_040000_create_production_optimized_indexes.php` - Production-optimized indexes

## Backup & Restore

### Automated Backup Script

#### Basic Usage
```bash
# Run backup manually
bash scripts/database/backup.sh

# Schedule via cron (daily at 2 AM)
0 2 * * * /path/to/project/scripts/database/backup.sh
```

#### Backup Features
- **Database Backup**: Supports MySQL and PostgreSQL with compression
- **Application Backup**: Backs up application files (excluding vendor/node_modules)
- **Automated Cleanup**: Keeps only last 7 days of backups
- **Integrity Verification**: Verifies backup file integrity
- **Notifications**: Optional webhook and email notifications
- **Disk Space Monitoring**: Ensures sufficient disk space

### Restore Script

#### Basic Usage
```bash
# List available backups
bash scripts/database/restore.sh --list

# Restore specific backup
bash scripts/database/restore.sh -f mysql_backup_laravel_20251106_040000.sql.gz

# Verify backup without restoring
bash scripts/database/restore.sh -f backup_file.sql.gz --verify-only

# Force restore without confirmation
bash scripts/database/restore.sh -f backup_file.sql.gz --force
```

#### Restore Features
- **Multiple Database Support**: MySQL and PostgreSQL
- **Selective Restore**: Database only or application files
- **Integrity Verification**: Verify backup before restore
- **Safety Confirmations**: Prevent accidental data loss
- **Database Recreation**: Automatically drops and recreates database
- **Permission Fixing**: Fixes file permissions after restore

### Backup Storage Structure
```
storage/backups/database/
├── mysql_backup_laravel_20251106_040000.sql.gz
├── postgres_backup_laravel_20251106_040000.sql.gz
├── app_files_20251106_040000.tar.gz
└── backup_manifest_20251106_040000.json
```

## Performance Optimization

### Indexing Strategy

#### Critical Indexes
The system includes comprehensive indexing for all major operations:

**Orders Table Indexes:**
```sql
-- Status and date queries (most common)
CREATE INDEX idx_orders_status_created ON orders(status, created_at);

-- Customer order queries
CREATE INDEX idx_orders_customer_status_date ON orders(customer_id, status, created_at);

-- Assignment queries
CREATE INDEX idx_orders_assigned_status ON orders(assigned_to, status, created_at);

-- Search optimization
CREATE INDEX idx_orders_number_status ON orders(order_number, status);
```

**Inventory Indexes:**
```sql
-- Unique stock records
CREATE UNIQUE INDEX idx_stocks_product_warehouse ON stocks(product_id, warehouse_id);

-- Low stock alerts
CREATE INDEX idx_stocks_low_stock ON stocks(quantity, min_stock_level) WHERE active = 1;

-- Product search
CREATE INDEX idx_products_category_active ON products(category_id, active);
```

#### Full-Text Search
```sql
-- MySQL full-text indexes
ALTER TABLE orders ADD FULLTEXT INDEX orders_search_idx (order_number, notes);
ALTER TABLE products ADD FULLTEXT INDEX products_search_idx (name, description);
ALTER TABLE customers ADD FULLTEXT INDEX customers_search_idx (name, address);
```

### Query Optimization

#### Eloquent Best Practices
```php
// Use specific columns
$orders = Order::select('id', 'order_number', 'status', 'created_at')
              ->where('status', 'pending')
              ->get();

// Eager loading relationships
$orders = Order::with('customer:id,name')
              ->where('status', 'pending')
              ->get();

// Efficient relationship queries
$orders = Order::whereHas('customer', function($query) {
                $query->where('city', 'Damascus');
            })->get();
```

#### Query Caching
```php
// Cache frequently accessed data
$orders = Cache::remember("orders_pending_{$user_id}", 3600, function() use ($user_id) {
    return Order::where('assigned_to', $user_id)
                ->where('status', 'pending')
                ->with('customer')
                ->get();
});
```

### Database Configuration

#### MySQL Optimization (my.cnf)
```ini
[mysqld]
# Connection settings
max_connections = 500
max_connect_errors = 10000
connect_timeout = 60
wait_timeout = 28800

# InnoDB settings
innodb_buffer_pool_size = 2G
innodb_log_file_size = 512M
innodb_log_buffer_size = 64M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query cache
query_cache_type = 1
query_cache_size = 256M
query_cache_limit = 2M

# Temporary tables
tmp_table_size = 256M
max_heap_table_size = 256M

# Slow query log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mysql-slow.log
long_query_time = 2
```

#### PostgreSQL Optimization (postgresql.conf)
```ini
# Connection settings
max_connections = 200
listen_addresses = '*'
port = 5432

# Memory settings
shared_buffers = 512MB
effective_cache_size = 2GB
work_mem = 16MB
maintenance_work_mem = 64MB

# Checkpoint settings
checkpoint_segments = 32
checkpoint_timeout = 15min
checkpoint_completion_target = 0.9

# WAL settings
wal_buffers = 16MB
max_wal_senders = 3
wal_level = replica

# Query planner
random_page_cost = 1.1
effective_io_concurrency = 200

# Logging
log_min_duration_statement = 1000
```

## Monitoring System

### Database Monitor Service

#### Usage
```php
use App\Services\DatabaseMonitor;

$monitor = new DatabaseMonitor('mysql');

// Get health status
$health = $monitor->getHealthStatus();

// Generate performance report
$report = $monitor->generatePerformanceReport();

// Send alert
$monitor->sendAlert('critical', 'Database connection failed');
```

#### Health Checks
- **Connection Status**: Database connectivity and response time
- **Performance Metrics**: Query performance, connection usage
- **Storage Monitoring**: Database size and table sizes
- **Index Usage**: Index effectiveness and unused indexes
- **Lock Monitoring**: Deadlocks and blocking queries
- **Error Tracking**: Error rates and recent errors

#### Alert Configuration
```php
// Add to config/database.php
'alert_email' => env('DB_ALERT_EMAIL'),
'alert_webhook' => env('DB_ALERT_WEBHOOK'),
```

### Performance Monitoring

#### Query Performance Tracking
```php
// Enable query logging
DB::enableQueryLog();

// Log slow queries
DB::listen(function($query) {
    if ($query->time > 1000) {
        Log::warning("Slow query", [
            'sql' => $query->sql,
            'time' => $query->time,
            'connection' => $query->connectionName
        ]);
    }
});
```

#### Database Statistics
- **Connection Pool Usage**: Monitor connection utilization
- **Query Statistics**: Track slow queries and frequently executed queries
- **Index Effectiveness**: Analyze index usage and performance impact
- **Storage Growth**: Monitor database and table size growth
- **Error Rates**: Track database errors and performance degradation

## Production Commands

### Database Seeder
```bash
# Run production seeder
php artisan db:seed --class=ProductionSeeder

# Seed specific tables
php artisan db:seed --class=ProductionSeeder --tables=users,warehouses,products
```

### Backup Commands
```bash
# Create database backup
php artisan db:backup --database=mysql --file=backup_20251106

# Create application backup
php artisan db:backup --type=application --file=app_backup_20251106
```

### Health Check Commands
```bash
# Database health check
php artisan db:health-check

# Performance report
php artisan db:performance-report

# Index optimization
php artisan db:optimize-indexes
```

## Security Features

### Connection Security
- **SSL/TLS Support**: Encrypted connections to database
- **Connection Pooling**: Secure connection management
- **Authentication**: Secure credential storage
- **Access Control**: Role-based database access

### Data Protection
- **Encryption at Rest**: Database-level encryption
- **Backup Encryption**: Encrypted backup files
- **Access Logging**: Database access monitoring
- **Audit Trails**: Comprehensive audit logging

### Security Best Practices
1. **Use Strong Passwords**: Complex database passwords
2. **Limit Database Users**: Minimal required permissions
3. **Network Security**: Firewall and VPN access
4. **Regular Updates**: Keep database software updated
5. **Backup Security**: Secure backup storage and encryption

## Maintenance Procedures

### Regular Maintenance Tasks

#### Daily Tasks
- Monitor database performance metrics
- Check backup completion status
- Review error logs and alerts
- Verify connection pool health

#### Weekly Tasks
- Analyze slow query logs
- Review index usage statistics
- Check database size growth
- Update database statistics

#### Monthly Tasks
- Perform database optimization
- Review and update indexes
- Analyze storage usage patterns
- Update security patches

### Performance Maintenance

#### Index Maintenance
```sql
-- MySQL index optimization
OPTIMIZE TABLE orders, order_processings, products;
ANALYZE TABLE orders, order_processings, products;

-- PostgreSQL index maintenance
REINDEX DATABASE your_database;
ANALYZE;
VACUUM FULL;
```

#### Statistics Update
```sql
-- Update table statistics
ANALYZE TABLE orders;

-- Check table health
SHOW TABLE STATUS LIKE 'orders';
```

### Automated Maintenance Scripts

#### Database Optimization Script
```bash
#!/bin/bash
# /scripts/maintenance/optimize-database.sh

# Update statistics
mysql -u root -p -e "ANALYZE TABLE orders, products, customers;"

# Optimize tables
mysql -u root -p -e "OPTIMIZE TABLE orders, products, customers;"

# Check index usage
mysql -u root -p -e "
    SELECT TABLE_NAME, INDEX_NAME, CARDINALITY 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = 'production_db'
    ORDER BY TABLE_NAME, INDEX_NAME;
"
```

## Troubleshooting

### Common Issues

#### Connection Issues
```bash
# Test database connection
php artisan tinker
DB::connection('mysql')->getPdo();

# Check connection pool status
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_%';"
```

#### Performance Issues
```bash
# Analyze slow queries
mysqldumpslow /var/log/mysql/mysql-slow.log

# Check index usage
mysql -u root -p -e "
    SELECT s.TABLE_SCHEMA, s.TABLE_NAME, s.INDEX_NAME, s.CARDINALITY
    FROM information_schema.STATISTICS s
    WHERE s.TABLE_SCHEMA = 'production_db'
    AND s.INDEX_NAME != 'PRIMARY'
    ORDER BY s.CARDINALITY ASC
    LIMIT 10;
"
```

#### Backup Issues
```bash
# Verify backup file
gunzip -t backup_file.sql.gz

# Check backup logs
tail -f storage/logs/backup.log

# Manual restore test
mysql -u root -p test_db < backup_file.sql
```

### Log Locations
- **Application Logs**: `storage/logs/laravel.log`
- **Database Logs**: `/var/log/mysql/` (MySQL) or `/var/log/postgresql/` (PostgreSQL)
- **Backup Logs**: `storage/logs/backup.log`
- **Migration Logs**: `storage/logs/migration.log`

### Performance Monitoring

#### Key Metrics to Watch
1. **Query Response Time**: < 200ms for 95% of queries
2. **Connection Usage**: < 80% of max connections
3. **Database Size Growth**: < 10% per month
4. **Slow Query Count**: < 100 per hour
5. **Cache Hit Rate**: > 95% for frequently accessed data

#### Alert Thresholds
- **Critical**: Query time > 1000ms, Connection usage > 90%
- **Warning**: Query time > 500ms, Connection usage > 70%
- **Info**: Database size growth > 5% per month

## Production Deployment Checklist

### Pre-Deployment
- [ ] Database server configured and optimized
- [ ] Connection pooling enabled
- [ ] Backup system tested and scheduled
- [ ] Monitoring system configured
- [ ] Security settings verified
- [ ] Performance benchmarks established

### During Deployment
- [ ] Run pre-flight checks
- [ ] Create database backup
- [ ] Execute migrations with verification
- [ ] Test application connectivity
- [ ] Verify all indexes are created
- [ ] Monitor performance metrics

### Post-Deployment
- [ ] Verify all functionality works
- [ ] Monitor error logs
- [ ] Check performance metrics
- [ ] Update monitoring dashboards
- [ ] Document any issues and resolutions
- [ ] Schedule first maintenance window

## Support and Maintenance

### Documentation
- `docs/database/QUERY_PERFORMANCE_STRATEGY.md` - Detailed performance optimization guide
- `docs/database/PRODUCTION_DATABASE_SETUP.md` - This comprehensive setup guide

### Scripts
- `scripts/database/backup.sh` - Automated backup script
- `scripts/database/restore.sh` - Database restore script
- `scripts/maintenance/optimize-database.sh` - Database optimization script

### Commands
- `php artisan db:migrate:production` - Production migration command
- `php artisan db:health-check` - Database health check
- `php artisan db:performance-report` - Performance report generation

### Emergency Contacts
- Database Administrator: [contact information]
- System Administrator: [contact information]
- Application Developer: [contact information]

---

## Conclusion

This production database setup provides a robust, scalable, and maintainable foundation for high-traffic applications. The system includes comprehensive monitoring, automated backup, performance optimization, and security features to ensure reliable operation.

For additional support or questions, refer to the troubleshooting section or contact the system administrator.