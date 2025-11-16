# Database Query Performance Strategy

## Overview
This document outlines the comprehensive query performance optimization strategy for high-traffic production systems, focusing on order management, inventory, and billing operations.

## Performance Goals
- **Response Time**: < 200ms for 95% of queries
- **Throughput**: Support 1000+ concurrent users
- **Scalability**: Handle 10M+ records efficiently
- **Uptime**: 99.9% availability

## Indexing Strategy

### 1. Primary Query Patterns Analysis

#### Order Management Queries
```sql
-- Most common query patterns:
SELECT * FROM orders WHERE status = ? AND created_at >= ? ORDER BY created_at DESC LIMIT ?
SELECT * FROM orders WHERE customer_id = ? AND status = ? ORDER BY created_at DESC
SELECT * FROM orders WHERE assigned_to = ? AND status IN (?, ?) ORDER BY priority DESC

-- Optimized indexes:
CREATE INDEX idx_orders_status_created ON orders(status, created_at);
CREATE INDEX idx_orders_customer_status ON orders(customer_id, status, created_at);
CREATE INDEX idx_orders_assigned_status ON orders(assigned_to, status, created_at);
```

#### Inventory Queries
```sql
-- Stock level queries:
SELECT * FROM stocks WHERE product_id = ? AND warehouse_id = ?
SELECT * FROM products WHERE category_id = ? AND active = 1 ORDER BY name
SELECT * FROM stocks WHERE quantity <= min_stock_level AND active = 1

-- Optimized indexes:
CREATE UNIQUE INDEX idx_stocks_product_warehouse ON stocks(product_id, warehouse_id);
CREATE INDEX idx_products_category_active ON products(category_id, active);
CREATE INDEX idx_stocks_low_stock ON stocks(quantity, min_stock_level) WHERE active = 1;
```

#### Customer Queries
```sql
-- Customer lookup:
SELECT * FROM customers WHERE phone = ? OR email = ?
SELECT * FROM customers WHERE sales_rep_id = ? AND active = 1 ORDER BY created_at DESC

-- Optimized indexes:
CREATE UNIQUE INDEX idx_customers_phone ON customers(phone) WHERE active = 1;
CREATE UNIQUE INDEX idx_customers_email ON customers(email) WHERE active = 1;
CREATE INDEX idx_customers_rep_active ON customers(sales_rep_id, active, created_at);
```

### 2. Composite Indexes Strategy

#### Multi-column Indexes for Common Combinations
```sql
-- Orders: Status + Customer + Date (most common pattern)
CREATE INDEX idx_orders_status_customer_date ON orders(status, customer_id, created_at);

-- Processings: Order + Stage + Status (workflow queries)
CREATE INDEX idx_processings_order_stage_status ON order_processings(order_id, stage_id, status);

-- Invoices: Customer + Status + Date (billing queries)
CREATE INDEX idx_invoices_customer_status_date ON invoices(customer_id, status, created_at);
```

#### Covering Indexes (Include All Required Columns)
```sql
-- For query: SELECT order_number, status, created_at FROM orders WHERE status = ?
CREATE INDEX idx_orders_status_covering ON orders(status, created_at, order_number, customer_id);
```

### 3. Partial Indexes (Filtered Indexes)

#### Active Records Only
```sql
-- Only index active products
CREATE INDEX idx_products_active ON products(category_id, active) WHERE active = 1;

-- Only index pending orders
CREATE INDEX idx_orders_pending ON orders(customer_id, created_at) WHERE status = 'pending';

-- Only index unresolved alerts
CREATE INDEX idx_alerts_unresolved ON alerts(product_id, created_at) WHERE resolved = 0;
```

## Query Optimization Techniques

### 1. Eloquent Query Optimization

#### Use Select Specific Columns
```php
// Bad: Loads all columns
$orders = Order::where('status', 'pending')->get();

// Good: Select only needed columns
$orders = Order::select('id', 'order_number', 'status', 'created_at')
              ->where('status', 'pending')
              ->get();
```

#### Use Eager Loading for Relationships
```php
// Bad: N+1 query problem
$orders = Order::where('status', 'pending')->get();
foreach ($orders as $order) {
    echo $order->customer->name; // Executes query for each order
}

// Good: Eager loading
$orders = Order::with('customer:id,name')
              ->where('status', 'pending')
              ->get();
```

#### Use WhereHas for Complex Relationships
```php
// Bad: Load all orders then filter
$orders = Order::with('customer')->get()
              ->filter(function($order) {
                  return $order->customer->city === 'Damascus';
              });

// Good: Query at database level
$orders = Order::whereHas('customer', function($query) {
                $query->where('city', 'Damascus');
            })->get();
```

### 2. Database-Level Optimizations

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

#### Database Views for Complex Queries
```sql
-- Create view for frequently accessed order summary
CREATE VIEW order_summary AS
SELECT 
    o.id,
    o.order_number,
    o.status,
    o.created_at,
    c.name as customer_name,
    c.phone as customer_phone,
    u.name as assigned_to_name,
    COUNT(oi.id) as items_count,
    SUM(oi.quantity * oi.unit_price) as total_amount
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.id
LEFT JOIN users u ON o.assigned_to = u.id
LEFT JOIN order_items oi ON o.id = oi.order_id
GROUP BY o.id, c.name, c.phone, u.name;
```

#### Stored Procedures for Complex Business Logic
```sql
DELIMITER $$
CREATE PROCEDURE GetOrderProcessingHistory(IN order_id INT)
BEGIN
    SELECT 
        op.id,
        ws.name as stage_name,
        op.status,
        op.assigned_to,
        op.stage_started_at,
        op.completed_at,
        TIMESTAMPDIFF(MINUTE, op.stage_started_at, op.completed_at) as duration_minutes
    FROM order_processings op
    JOIN work_stages ws ON op.stage_id = ws.id
    WHERE op.order_id = order_id
    ORDER BY ws.order;
END$$
DELIMITER ;
```

### 3. Connection Pooling and Query Batching

#### Connection Pooling Configuration
```php
// In config/database.php
'mysql' => [
    'driver' => 'mysql',
    'pool' => [
        'min_connections' => 10,
        'max_connections' => 100,
        'acquire_timeout' => 60,
        'idle_timeout' => 600,
    ],
];
```

#### Batch Processing for Large Datasets
```php
// Process large datasets in chunks
Order::where('created_at', '>=', now()->subYear())
     ->chunk(1000, function($orders) {
         foreach ($orders as $order) {
             // Process each order
             $this->processOrderAnalytics($order);
         }
     });
```

## Performance Monitoring

### 1. Query Performance Tracking

#### Laravel Query Log
```php
// Enable query logging in development
DB::enableQueryLog();

// Log slow queries
DB::listen(function($query) {
    if ($query->time > 1000) { // Log queries > 1 second
        Log::warning("Slow query detected", [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time,
            'connection' => $query->connectionName
        ]);
    }
});
```

#### Database-Specific Monitoring

**MySQL Performance Insights:**
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Analyze slow queries
SELECT 
    query_time,
    lock_time,
    rows_sent,
    rows_examined,
    sql_text
FROM mysql.slow_log
ORDER BY query_time DESC
LIMIT 10;
```

**PostgreSQL Monitoring:**
```sql
-- Enable query statistics
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

-- Find most time-consuming queries
SELECT 
    query,
    calls,
    total_time,
    mean_time,
    rows
FROM pg_stat_statements
ORDER BY total_time DESC
LIMIT 10;
```

### 2. Index Usage Analysis

#### MySQL Index Usage
```sql
-- Check index usage
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    NON_UNIQUE,
    SEQ_IN_INDEX,
    COLUMN_NAME,
    CARDINALITY
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'your_database'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
```

#### PostgreSQL Index Usage
```sql
-- Check index usage statistics
SELECT 
    schemaname,
    tablename,
    indexname,
    idx_scan,
    idx_tup_read,
    idx_tup_fetch
FROM pg_stat_user_indexes
ORDER BY idx_scan DESC;
```

## Database Configuration Optimization

### 1. MySQL Configuration (my.cnf)
```ini
[mysqld]
# Connection settings
max_connections = 500
max_connect_errors = 10000
connect_timeout = 60
wait_timeout = 28800

# Buffer settings
innodb_buffer_pool_size = 2G
innodb_log_file_size = 512M
innodb_log_buffer_size = 64M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query cache (MySQL 5.7 and earlier)
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

# Binary logging
log_bin = /var/log/mysql/mysql-bin.log
expire_logs_days = 7
max_binlog_size = 100M
```

### 2. PostgreSQL Configuration (postgresql.conf)
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
log_destination = 'stderr'
logging_collector = on
log_directory = 'pg_log'
log_min_duration_statement = 1000
log_line_prefix = '%t [%p]: [%l-1] user=%u,db=%d,app=%a,client=%h '
```

## Maintenance Procedures

### 1. Regular Index Maintenance

#### MySQL Index Optimization
```sql
-- Analyze table statistics
ANALYZE TABLE orders, order_processings, products, stocks;

-- Optimize tables
OPTIMIZE TABLE orders, order_processings, products, stocks;
```

#### PostgreSQL Index Maintenance
```sql
-- Reindex entire database
REINDEX DATABASE your_database;

-- Analyze table statistics
ANALYZE;

-- Vacuum to reclaim space
VACUUM FULL;
```

### 2. Database Statistics Updates

#### MySQL Statistics
```sql
-- Update table statistics
OPTIMIZE TABLE orders;
ANALYZE TABLE order_processings;
ANALYZE TABLE products;

-- Check table health
SHOW TABLE STATUS LIKE 'orders';
```

#### PostgreSQL Statistics
```sql
-- Update statistics
ANALYZE;

-- Check table sizes
SELECT 
    schemaname,
    tablename,
    attname,
    inherited,
    null_frac,
    avg_width,
    n_distinct,
    most_common_vals
FROM pg_stats
WHERE schemaname = 'public'
ORDER BY tablename, attname;
```

## Performance Testing

### 1. Load Testing Queries

#### Simulate High Load
```php
// Test query performance under load
$start = microtime(true);

for ($i = 0; $i < 1000; $i++) {
    $orders = Order::where('status', 'pending')
                   ->with('customer')
                   ->limit(50)
                   ->get();
}

$duration = microtime(true) - $start;
Log::info("Query performance test: {$duration} seconds for 1000 iterations");
```

#### Database Benchmarking
```bash
# MySQL benchmark
mysqlslap --host=localhost --user=root --password --database=laravel \
         --query="SELECT * FROM orders WHERE status='pending' LIMIT 10" \
         --concurrency=10 --iterations=100

# PostgreSQL benchmark
pgbench -h localhost -U postgres -d laravel -c 10 -j 4 -T 60 \
        -S "SELECT * FROM orders WHERE status='pending' LIMIT 10;"
```

### 2. Query Plan Analysis

#### MySQL EXPLAIN
```sql
EXPLAIN SELECT o.*, c.name as customer_name 
FROM orders o 
JOIN customers c ON o.customer_id = c.id 
WHERE o.status = 'pending' 
ORDER BY o.created_at DESC 
LIMIT 20;
```

#### PostgreSQL EXPLAIN
```sql
EXPLAIN (ANALYZE, BUFFERS) 
SELECT o.*, c.name as customer_name 
FROM orders o 
JOIN customers c ON o.customer_id = c.id 
WHERE o.status = 'pending' 
ORDER BY o.created_at DESC 
LIMIT 20;
```

## Alert Thresholds

### Performance Alerts
- **Query Time**: Alert if > 1000ms
- **Slow Query Count**: Alert if > 100/hour
- **Connection Pool**: Alert if > 80% utilized
- **Database Size**: Alert if growth > 10%/month
- **Index Usage**: Alert if index usage < 95%

### Health Metrics
- **Uptime**: < 99.9%
- **Error Rate**: > 0.1%
- **Deadlock Count**: > 10/hour
- **Lock Wait Time**: > 5000ms average

## Implementation Checklist

- [ ] Implement composite indexes for common query patterns
- [ ] Enable query caching for frequently accessed data
- [ ] Configure connection pooling
- [ ] Set up performance monitoring
- [ ] Configure slow query logging
- [ ] Implement automated maintenance procedures
- [ ] Create database views for complex queries
- [ ] Optimize database configuration
- [ ] Set up performance alerts
- [ ] Create performance testing framework

## Conclusion

This strategy provides a comprehensive approach to database query optimization for high-traffic systems. Regular monitoring, maintenance, and optimization are essential for maintaining optimal performance as data volume and user load increase.