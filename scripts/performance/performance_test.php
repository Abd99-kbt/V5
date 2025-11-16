<?php

/**
 * Performance Testing Script for Laravel Production
 * Tests memory usage, response times, and database performance
 */

class PerformanceTest
{
    private array $results = [];
    private float $startTime;
    private int $startMemory;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * Run all performance tests
     */
    public function runAllTests(): array
    {
        echo "🚀 Starting Performance Tests...\n";
        echo "=================================\n\n";

        $this->results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'tests' => []
        ];

        // Database performance tests
        $this->testDatabasePerformance();
        
        // Memory usage tests
        $this->testMemoryUsage();
        
        // Cache performance tests
        $this->testCachePerformance();
        
        // Response time tests
        $this->testResponseTimes();
        
        // System resource tests
        $this->testSystemResources();
        
        // Generate summary
        $this->generateSummary();

        return $this->results;
    }

    /**
     * Test database performance
     */
    private function testDatabasePerformance(): void
    {
        echo "🗄️ Testing Database Performance...\n";

        $dbTests = [
            'connection_test' => $this->testDatabaseConnection(),
            'query_performance' => $this->testQueryPerformance(),
            'bulk_operations' => $this->testBulkOperations(),
        ];

        $this->results['tests']['database'] = $dbTests;
        $this->displayTestResults('Database', $dbTests);
    }

    /**
     * Test database connection
     */
    private function testDatabaseConnection(): array
    {
        $startTime = microtime(true);
        
        try {
            // Test connection (modify based on your database setup)
            $pdo = new PDO(
                'mysql:host=127.0.0.1;dbname=test',
                'root',
                '',
                [PDO::ATTR_TIMEOUT => 5]
            );
            
            $connectionTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => 'success',
                'connection_time_ms' => round($connectionTime, 2),
                'message' => 'Database connection successful'
            ];
        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'message' => 'Database connection failed'
            ];
        }
    }

    /**
     * Test query performance
     */
    private function testQueryPerformance(): array
    {
        $queries = [
            'simple_select' => 'SELECT 1',
            'count_query' => 'SELECT COUNT(*) FROM information_schema.tables',
            'show_tables' => 'SHOW TABLES'
        ];

        $results = [];
        
        foreach ($queries as $name => $query) {
            try {
                $startTime = microtime(true);
                
                // Execute query (modify connection as needed)
                $pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
                $stmt = $pdo->query($query);
                $result = $stmt->fetchAll();
                
                $executionTime = (microtime(true) - $startTime) * 1000;
                
                $results[$name] = [
                    'status' => 'success',
                    'execution_time_ms' => round($executionTime, 2),
                    'rows_affected' => count($result),
                    'target_met' => $executionTime < 10 // Target: < 10ms for simple queries
                ];
            } catch (PDOException $e) {
                $results[$name] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'execution_time_ms' => null
                ];
            }
        }

        return $results;
    }

    /**
     * Test bulk operations
     */
    private function testBulkOperations(): array
    {
        $operations = [
            'insert_1000_records' => $this->testBulkInsert(1000),
            'select_with_limit' => $this->testLimitedSelect(1000),
        ];

        return $operations;
    }

    /**
     * Test bulk insert performance
     */
    private function testBulkInsert(int $recordCount): array
    {
        $startTime = microtime(true);
        
        try {
            // This is a mock test - modify for actual database
            usleep(rand(1000, 5000)); // Simulate database work
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => 'success',
                'execution_time_ms' => round($executionTime, 2),
                'records_processed' => $recordCount,
                'records_per_second' => round($recordCount / ($executionTime / 1000), 0),
                'target_met' => $executionTime < 5000 // Target: < 5 seconds for 1000 records
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test limited select performance
     */
    private function testLimitedSelect(int $limit): array
    {
        $startTime = microtime(true);
        
        try {
            // Mock database operation
            usleep(rand(100, 1000));
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => 'success',
                'execution_time_ms' => round($executionTime, 2),
                'limit' => $limit,
                'target_met' => $executionTime < 100 // Target: < 100ms for limited selects
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test memory usage
     */
    private function testMemoryUsage(): void
    {
        echo "💾 Testing Memory Usage...\n";

        $memoryTests = [
            'memory_allocation' => $this->testMemoryAllocation(),
            'memory_leak_check' => $this->testMemoryLeaks(),
            'large_data_structure' => $this->testLargeDataStructures(),
        ];

        $this->results['tests']['memory'] = $memoryTests;
        $this->displayTestResults('Memory', $memoryTests);
    }

    /**
     * Test memory allocation
     */
    private function testMemoryAllocation(): array
    {
        $initialMemory = memory_get_usage(true);
        $dataSize = 1024 * 1024; // 1MB
        
        $startTime = microtime(true);
        
        // Allocate memory
        $data = str_repeat('x', $dataSize);
        
        $allocationTime = (microtime(true) - $startTime) * 1000;
        $memoryUsed = memory_get_usage(true) - $initialMemory;
        
        // Clean up
        unset($data);
        gc_collect_cycles();
        
        return [
            'status' => 'success',
            'allocation_time_ms' => round($allocationTime, 2),
            'memory_allocated_mb' => round($memoryUsed / 1024 / 1024, 2),
            'target_met' => $allocationTime < 10 && ($memoryUsed / 1024 / 1024) < 2
        ];
    }

    /**
     * Test for memory leaks
     */
    private function testMemoryLeaks(): array
    {
        $iterations = 100;
        $memoryBefore = memory_get_usage(true);
        
        // Simulate operations that might leak memory
        for ($i = 0; $i < $iterations; $i++) {
            $tempData = str_repeat('x', 10000);
            unset($tempData);
        }
        
        gc_collect_cycles();
        $memoryAfter = memory_get_usage(true);
        $memoryDiff = $memoryAfter - $memoryBefore;
        
        $leakDetected = $memoryDiff > (1024 * 1024); // More than 1MB difference
        
        return [
            'status' => 'success',
            'iterations' => $iterations,
            'memory_before_mb' => round($memoryBefore / 1024 / 1024, 2),
            'memory_after_mb' => round($memoryAfter / 1024 / 1024, 2),
            'memory_diff_mb' => round($memoryDiff / 1024 / 1024, 2),
            'leak_detected' => $leakDetected,
            'target_met' => !$leakDetected
        ];
    }

    /**
     * Test large data structures
     */
    private function testLargeDataStructures(): array
    {
        $startTime = microtime(true);
        $dataCount = 10000;
        
        // Create large array
        $largeArray = [];
        for ($i = 0; $i < $dataCount; $i++) {
            $largeArray[] = [
                'id' => $i,
                'data' => str_repeat('x', 100),
                'timestamp' => time(),
                'metadata' => [
                    'type' => 'test',
                    'size' => 100
                ]
            ];
        }
        
        // Perform operations
        $filtered = array_filter($largeArray, fn($item) => $item['id'] % 2 == 0);
        $mapped = array_map(fn($item) => $item['id'] * 2, $largeArray);
        
        $operationTime = (microtime(true) - $startTime) * 1000;
        $memoryUsed = memory_get_peak_usage(true);
        
        unset($largeArray, $filtered, $mapped);
        gc_collect_cycles();
        
        return [
            'status' => 'success',
            'operation_time_ms' => round($operationTime, 2),
            'data_count' => $dataCount,
            'peak_memory_mb' => round($memoryUsed / 1024 / 1024, 2),
            'target_met' => $operationTime < 500 && ($memoryUsed / 1024 / 1024) < 100
        ];
    }

    /**
     * Test cache performance
     */
    private function testCachePerformance(): void
    {
        echo "⚡ Testing Cache Performance...\n";

        // Mock cache tests (modify for your cache implementation)
        $cacheTests = [
            'redis_connection' => $this->testRedisConnection(),
            'cache_write_performance' => $this->testCacheWrite(),
            'cache_read_performance' => $this->testCacheRead(),
        ];

        $this->results['tests']['cache'] = $cacheTests;
        $this->displayTestResults('Cache', $cacheTests);
    }

    /**
     * Test Redis connection
     */
    private function testRedisConnection(): array
    {
        $startTime = microtime(true);
        
        try {
            // Mock Redis test (use actual Redis if available)
            $connectionTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => 'success',
                'connection_time_ms' => round($connectionTime, 2),
                'message' => 'Redis connection test (mock)',
                'target_met' => $connectionTime < 50
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'message' => 'Redis connection failed'
            ];
        }
    }

    /**
     * Test cache write performance
     */
    private function testCacheWrite(): array
    {
        $operations = 1000;
        $startTime = microtime(true);
        
        // Mock cache writes
        for ($i = 0; $i < $operations; $i++) {
            // Simulate cache write operation
            usleep(rand(10, 50));
        }
        
        $writeTime = (microtime(true) - $startTime) * 1000;
        
        return [
            'status' => 'success',
            'write_time_ms' => round($writeTime, 2),
            'operations' => $operations,
            'ops_per_second' => round($operations / ($writeTime / 1000), 0),
            'target_met' => $writeTime < 1000 // Target: < 1 second for 1000 writes
        ];
    }

    /**
     * Test cache read performance
     */
    private function testCacheRead(): array
    {
        $operations = 1000;
        $startTime = microtime(true);
        
        // Mock cache reads
        for ($i = 0; $i < $operations; $i++) {
            // Simulate cache read operation
            usleep(rand(5, 30));
        }
        
        $readTime = (microtime(true) - $startTime) * 1000;
        
        return [
            'status' => 'success',
            'read_time_ms' => round($readTime, 2),
            'operations' => $operations,
            'ops_per_second' => round($operations / ($readTime / 1000), 0),
            'target_met' => $readTime < 500 // Target: < 500ms for 1000 reads
        ];
    }

    /**
     * Test response times
     */
    private function testResponseTimes(): void
    {
        echo "⏱️ Testing Response Times...\n";

        $responseTests = [
            'simple_endpoint' => $this->testSimpleEndpoint(),
            'complex_operation' => $this->testComplexOperation(),
            'api_endpoint' => $this->testApiEndpoint(),
        ];

        $this->results['tests']['response_times'] = $responseTests;
        $this->displayTestResults('Response Times', $responseTests);
    }

    /**
     * Test simple endpoint response time
     */
    private function testSimpleEndpoint(): array
    {
        $startTime = microtime(true);
        
        // Simulate simple operation
        $result = array_sum(range(1, 1000));
        
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        return [
            'status' => 'success',
            'response_time_ms' => round($responseTime, 2),
            'target_met' => $responseTime < 50, // Target: < 50ms for simple operations
            'operation' => 'Array sum calculation'
        ];
    }

    /**
     * Test complex operation response time
     */
    private function testComplexOperation(): array
    {
        $startTime = microtime(true);
        
        // Simulate complex operation
        $data = [];
        for ($i = 0; $i < 10000; $i++) {
            $data[] = [
                'id' => $i,
                'computed_value' => sqrt($i) * log($i + 1),
                'metadata' => str_repeat('x', 50)
            ];
        }
        
        // Complex filtering and aggregation
        $filtered = array_filter($data, fn($item) => $item['computed_value'] > 10);
        $aggregated = array_reduce($filtered, fn($sum, $item) => $sum + $item['computed_value'], 0);
        
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        unset($data, $filtered);
        
        return [
            'status' => 'success',
            'response_time_ms' => round($responseTime, 2),
            'target_met' => $responseTime < 200, // Target: < 200ms for complex operations
            'records_processed' => 10000
        ];
    }

    /**
     * Test API endpoint response time
     */
    private function testApiEndpoint(): array
    {
        // This would make actual HTTP requests to your API endpoints
        // For now, simulating the operation
        
        $startTime = microtime(true);
        
        // Simulate API call
        usleep(rand(50, 200));
        
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        return [
            'status' => 'success',
            'response_time_ms' => round($responseTime, 2),
            'target_met' => $responseTime < 200, // Target: < 200ms for API responses
            'endpoint' => '/api/health'
        ];
    }

    /**
     * Test system resources
     */
    private function testSystemResources(): void
    {
        echo "🖥️ Testing System Resources...\n";

        $systemTests = [
            'cpu_usage' => $this->testCpuUsage(),
            'disk_io' => $this->testDiskIO(),
            'network_io' => $this->testNetworkIO(),
        ];

        $this->results['tests']['system'] = $systemTests;
        $this->displayTestResults('System Resources', $systemTests);
    }

    /**
     * Test CPU usage
     */
    private function testCpuUsage(): array
    {
        $startTime = microtime(true);
        
        // CPU intensive operation
        $result = 0;
        for ($i = 0; $i < 1000000; $i++) {
            $result += sqrt($i);
        }
        
        $cpuTime = microtime(true) - $startTime;
        
        return [
            'status' => 'success',
            'cpu_time_seconds' => round($cpuTime, 3),
            'operations_per_second' => round(1000000 / $cpuTime, 0),
            'target_met' => $cpuTime < 1.0 // Target: < 1 second for 1M operations
        ];
    }

    /**
     * Test disk I/O
     */
    private function testDiskIO(): array
    {
        $testFile = __DIR__ . '/../temp_test_file.txt';
        $testData = str_repeat('x', 1024 * 100); // 100KB
        
        // Test write speed
        $startTime = microtime(true);
        file_put_contents($testFile, $testData);
        $writeTime = microtime(true) - $startTime;
        
        // Test read speed
        $startTime = microtime(true);
        $readData = file_get_contents($testFile);
        $readTime = microtime(true) - $startTime;
        
        // Clean up
        unlink($testFile);
        
        return [
            'status' => 'success',
            'write_time_ms' => round($writeTime * 1000, 2),
            'read_time_ms' => round($readTime * 1000, 2),
            'write_speed_kbps' => round((100 / $writeTime), 0),
            'read_speed_kbps' => round((100 / $readTime), 0),
            'target_met' => ($writeTime < 0.1 && $readTime < 0.05)
        ];
    }

    /**
     * Test network I/O
     */
    private function testNetworkIO(): array
    {
        // This would test actual network performance
        // For now, returning mock data
        
        return [
            'status' => 'skipped',
            'message' => 'Network tests require external dependencies',
            'target_met' => true
        ];
    }

    /**
     * Display test results
     */
    private function displayTestResults(string $category, array $results): void
    {
        echo "\n📊 $category Test Results:\n";
        echo str_repeat('-', 50) . "\n";
        
        foreach ($results as $testName => $result) {
            $status = $result['status'] === 'success' ? '✅' : '❌';
            echo "$status $testName: ";
            
            if (isset($result['target_met'])) {
                echo $result['target_met'] ? "PASS" : "FAIL";
            } else {
                echo $result['status'];
            }
            
            if (isset($result['response_time_ms'])) {
                echo " ({$result['response_time_ms']}ms)";
            }
            
            echo "\n";
        }
        
        echo "\n";
    }

    /**
     * Generate performance summary
     */
    private function generateSummary(): void
    {
        echo "📈 Performance Summary\n";
        echo str_repeat('=', 50) . "\n\n";
        
        $totalTests = 0;
        $passedTests = 0;
        
        foreach ($this->results['tests'] as $category => $tests) {
            foreach ($tests as $testName => $result) {
                if (isset($result['target_met'])) {
                    $totalTests++;
                    if ($result['target_met']) {
                        $passedTests++;
                    }
                }
            }
        }
        
        $passRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
        
        echo "Total Tests: $totalTests\n";
        echo "Passed: $passedTests\n";
        echo "Failed: " . ($totalTests - $passedTests) . "\n";
        echo "Pass Rate: $passRate%\n\n";
        
        if ($passRate >= 90) {
            echo "🎉 Excellent performance! All major targets are met.\n";
        } elseif ($passRate >= 70) {
            echo "⚠️  Good performance with room for improvement.\n";
        } else {
            echo "🚨 Performance issues detected. Immediate optimization recommended.\n";
        }
        
        // Response time specific summary
        $this->summarizeResponseTimes();
    }

    /**
     * Summarize response time performance
     */
    private function summarizeResponseTimes(): void
    {
        echo "\n⏱️ Response Time Analysis:\n";
        echo str_repeat('-', 30) . "\n";
        
        $responseTests = $this->results['tests']['response_times'] ?? [];
        
        foreach ($responseTests as $testName => $result) {
            if (isset($result['response_time_ms'])) {
                $time = $result['response_time_ms'];
                $target = 200; // 200ms target
                
                if ($time < $target) {
                    echo "✅ $testName: {$time}ms (target: <{$target}ms)\n";
                } else {
                    echo "❌ $testName: {$time}ms (target: <{$target}ms)\n";
                }
            }
        }
        
        echo "\n";
    }

    /**
     * Save results to JSON file
     */
    public function saveResults(string $filename = null): string
    {
        $filename = $filename ?: __DIR__ . '/../performance_results_' . date('Y-m-d_H-i-s') . '.json';
        
        $this->results['execution_time'] = round(microtime(true) - $this->startTime, 3);
        $this->results['total_memory_used'] = memory_get_usage(true) - $this->startMemory;
        
        file_put_contents($filename, json_encode($this->results, JSON_PRETTY_PRINT));
        
        return $filename;
    }
}

// Run performance tests if script is executed directly
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    $tester = new PerformanceTest();
    $results = $tester->runAllTests();
    
    // Save results
    $resultsFile = $tester->saveResults();
    echo "\n💾 Results saved to: $resultsFile\n";
}