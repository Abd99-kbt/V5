<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConcurrentUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // إعداد بيانات الاختبار
        $this->setupTestData();
    }

    /** @test */
    public function test_concurrent_user_authentication()
    {
        $userCount = 50;
        $authAttemptsPerUser = 10;
        $users = User::factory()->count($userCount)->create();
        
        $startTime = microtime(true);
        $successfulAuths = 0;
        $failedAuths = 0;
        
        // محاكاة المصادقة المتزامنة
        for ($i = 0; $i < $userCount; $i++) {
            $user = $users->get($i);
            
            for ($j = 0; $j < $authAttemptsPerUser; $j++) {
                // محاكاة محاولة تسجيل دخول
                $this->actingAs($user);
                
                // محاكاة عملية تسجيل دخول
                try {
                    $response = $this->post('/login', [
                        'username' => $user->username,
                        'password' => 'password'
                    ]);
                    
                    if ($response->status() === 200 || $response->status() === 302) {
                        $successfulAuths++;
                    } else {
                        $failedAuths++;
                    }
                } catch (\Exception $e) {
                    $failedAuths++;
                }
                
                usleep(rand(1000, 5000)); // تأخير عشوائي 1-5ms
            }
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalAttempts = $userCount * $authAttemptsPerUser;
        $successRate = $successfulAuths / $totalAttempts;
        $attemptsPerSecond = $totalAttempts / $totalTime;
        
        $this->assertGreaterThan(0.8, $successRate, 'Authentication success rate should be above 80%');
        $this->assertGreaterThan(10, $attemptsPerSecond, 'Should handle concurrent authentication efficiently');
    }

    /** @test */
    public function test_concurrent_order_creation()
    {
        $userCount = 20;
        $ordersPerUser = 5;
        $users = User::factory()->count($userCount)->create();
        $customers = Customer::factory()->count(100)->create();
        
        $startTime = microtime(true);
        $successfulCreations = 0;
        $failedCreations = 0;
        
        // محاكاة إنشاء الطلبات المتزامنة
        for ($i = 0; $i < $userCount; $i++) {
            $user = $users->get($i);
            $user->assignRole('admin');
            
            for ($j = 0; $j < $ordersPerUser; $j++) {
                $customer = $customers->random();
                
                $this->actingAs($user);
                
                try {
                    $orderData = [
                        'customer_id' => $customer->id,
                        'total_amount' => rand(100, 1000) / 100,
                        'status' => 'pending'
                    ];
                    
                    $response = $this->post('/admin/orders', $orderData);
                    
                    if ($response->status() === 201) {
                        $successfulCreations++;
                    } else {
                        $failedCreations++;
                    }
                } catch (\Exception $e) {
                    $failedCreations++;
                }
                
                usleep(rand(2000, 10000)); // تأخير 2-10ms
            }
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalAttempts = $userCount * $ordersPerUser;
        $successRate = $successfulCreations / $totalAttempts;
        $creationsPerSecond = $totalAttempts / $totalTime;
        
        $this->assertGreaterThan(0.9, $successRate, 'Order creation success rate should be above 90%');
        $this->assertGreaterThan(5, $creationsPerSecond, 'Should handle concurrent order creation efficiently');
    }

    /** @test */
    public function test_concurrent_database_queries()
    {
        $queryThreads = 15;
        $queriesPerThread = 50;
        
        $startTime = microtime(true);
        $successfulQueries = 0;
        $failedQueries = 0;
        
        // محاكاة استعلامات متزامنة
        for ($i = 0; $i < $queryThreads; $i++) {
            for ($j = 0; $j < $queriesPerThread; $j++) {
                try {
                    // استعلامات متنوعة
                    $queryType = $j % 4;
                    
                    switch ($queryType) {
                        case 0:
                            // استعلام بسيط
                            Order::count();
                            break;
                        case 1:
                            // استعلام مع conditions
                            Order::where('status', 'pending')->count();
                            break;
                        case 2:
                            // استعلام مع join
                            \DB::table('orders')
                                ->join('customers', 'orders.customer_id', '=', 'customers.id')
                                ->count();
                            break;
                        case 3:
                            // استعلام مع aggregation
                            \DB::table('orders')
                                ->selectRaw('COUNT(*) as total, AVG(total_amount) as avg_amount')
                                ->first();
                            break;
                    }
                    
                    $successfulQueries++;
                } catch (\Exception $e) {
                    $failedQueries++;
                }
                
                usleep(rand(100, 1000)); // تأخير 0.1-1ms
            }
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalQueries = $queryThreads * $queriesPerThread;
        $successRate = $successfulQueries / $totalQueries;
        $queriesPerSecond = $totalQueries / $totalTime;
        
        $this->assertGreaterThan(0.95, $successRate, 'Database query success rate should be above 95%');
        $this->assertGreaterThan(100, $queriesPerSecond, 'Should handle concurrent database queries efficiently');
    }

    /** @test */
    public function test_concurrent_cache_operations()
    {
        $cacheThreads = 10;
        $operationsPerThread = 100;
        
        $startTime = microtime(true);
        $successfulOperations = 0;
        $failedOperations = 0;
        
        for ($i = 0; $i < $cacheThreads; $i++) {
            for ($j = 0; $j < $operationsPerThread; $j++) {
                $key = "concurrent_test_key_{$j}";
                $value = "test_value_" . uniqid();
                
                try {
                    // عمليات cache متنوعة
                    $operation = $j % 3;
                    
                    switch ($operation) {
                        case 0:
                            // write
                            Cache::put($key, $value, 60);
                            $successfulOperations++;
                            break;
                        case 1:
                            // read
                            $cachedValue = Cache::get($key);
                            if ($cachedValue !== null) {
                                $successfulOperations++;
                            } else {
                                $failedOperations++;
                            }
                            break;
                        case 2:
                            // delete
                            Cache::forget($key);
                            $successfulOperations++;
                            break;
                    }
                } catch (\Exception $e) {
                    $failedOperations++;
                }
                
                usleep(rand(50, 500)); // تأخير 0.05-0.5ms
            }
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalOperations = $cacheThreads * $operationsPerThread;
        $successRate = $successfulOperations / $totalOperations;
        $operationsPerSecond = $totalOperations / $totalTime;
        
        $this->assertGreaterThan(0.9, $successRate, 'Cache operation success rate should be above 90%');
        $this->assertGreaterThan(1000, $operationsPerSecond, 'Should handle concurrent cache operations efficiently');
    }

    /** @test */
    public function test_concurrent_file_operations()
    {
        $fileThreads = 5;
        $operationsPerThread = 20;
        
        $startTime = microtime(true);
        $successfulOperations = 0;
        $failedOperations = 0;
        
        for ($i = 0; $i < $fileThreads; $i++) {
            for ($j = 0; $j < $operationsPerThread; $j++) {
                $filename = "concurrent_test_" . uniqid() . ".txt";
                $content = "Test content for file " . $j;
                
                try {
                    $operation = $j % 2;
                    
                    switch ($operation) {
                        case 0:
                            // write file
                            file_put_contents(storage_path("app/public/{$filename}"), $content);
                            $successfulOperations++;
                            break;
                        case 1:
                            // read file
                            $readContent = file_get_contents(storage_path("app/public/{$filename}"));
                            if ($readContent === $content) {
                                $successfulOperations++;
                            } else {
                                $failedOperations++;
                            }
                            unlink(storage_path("app/public/{$filename}")); // cleanup
                            break;
                    }
                } catch (\Exception $e) {
                    $failedOperations++;
                    // cleanup on error
                    if (file_exists(storage_path("app/public/{$filename}"))) {
                        unlink(storage_path("app/public/{$filename}"));
                    }
                }
                
                usleep(rand(1000, 5000)); // تأخير 1-5ms
            }
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalOperations = $fileThreads * $operationsPerThread;
        $successRate = $successfulOperations / $totalOperations;
        $operationsPerSecond = $totalOperations / $totalTime;
        
        $this->assertGreaterThan(0.8, $successRate, 'File operation success rate should be above 80%');
        $this->assertGreaterThan(1, $operationsPerSecond, 'Should handle concurrent file operations');
    }

    /** @test */
    public function test_concurrent_api_requests()
    {
        $apiThreads = 8;
        $requestsPerThread = 20;
        
        $startTime = microtime(true);
        $successfulRequests = 0;
        $failedRequests = 0;
        
        for ($i = 0; $i < $apiThreads; $i++) {
            for ($j = 0; $j < $requestsPerThread; $j++) {
                $endpoint = $j % 5;
                $url = '';
                
                switch ($endpoint) {
                    case 0:
                        $url = '/api/orders';
                        break;
                    case 1:
                        $url = '/api/customers';
                        break;
                    case 2:
                        $url = '/api/users';
                        break;
                    case 3:
                        $url = '/api/orders?per_page=10';
                        break;
                    case 4:
                        $url = '/api/orders?status=pending';
                        break;
                }
                
                try {
                    $response = $this->getJson($url);
                    
                    if ($response->status() === 200) {
                        $successfulRequests++;
                    } else {
                        $failedRequests++;
                    }
                } catch (\Exception $e) {
                    $failedRequests++;
                }
                
                usleep(rand(500, 2000)); // تأخير 0.5-2ms
            }
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalRequests = $apiThreads * $requestsPerThread;
        $successRate = $successfulRequests / $totalRequests;
        $requestsPerSecond = $totalRequests / $totalTime;
        
        $this->assertGreaterThan(0.9, $successRate, 'API request success rate should be above 90%');
        $this->assertGreaterThan(20, $requestsPerSecond, 'Should handle concurrent API requests efficiently');
    }

    /** @test */
    public function test_concurrent_mixed_operations()
    {
        $operationThreads = 12;
        $operationsPerThread = 15;
        
        $startTime = microtime(true);
        $successfulOperations = 0;
        $failedOperations = 0;
        
        for ($i = 0; $i < $operationThreads; $i++) {
            for ($j = 0; $j < $operationsPerThread; $j++) {
                $operation = $j % 6;
                $user = User::factory()->create();
                
                try {
                    switch ($operation) {
                        case 0:
                            // Authentication
                            $this->actingAs($user);
                            $successfulOperations++;
                            break;
                        case 1:
                            // Database query
                            Order::count();
                            $successfulOperations++;
                            break;
                        case 2:
                            // Cache operation
                            Cache::put("test_key_{$i}_{$j}", "test_value", 60);
                            $successfulOperations++;
                            break;
                        case 3:
                            // File operation
                            $filename = "mixed_test_" . uniqid() . ".txt";
                            file_put_contents(storage_path("app/public/{$filename}"), "test content");
                            if (file_exists(storage_path("app/public/{$filename}"))) {
                                unlink(storage_path("app/public/{$filename}"));
                            }
                            $successfulOperations++;
                            break;
                        case 4:
                            // API call
                            $response = $this->getJson('/api/orders');
                            if ($response->status() === 200) {
                                $successfulOperations++;
                            } else {
                                $failedOperations++;
                            }
                            break;
                        case 5:
                            // Log operation
                            Log::info("Test log message {$i}_{$j}");
                            $successfulOperations++;
                            break;
                    }
                } catch (\Exception $e) {
                    $failedOperations++;
                }
                
                usleep(rand(1000, 3000)); // تأخير 1-3ms
            }
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalOperations = $operationThreads * $operationsPerThread;
        $successRate = $successfulOperations / $totalOperations;
        $operationsPerSecond = $totalOperations / $totalTime;
        
        $this->assertGreaterThan(0.85, $successRate, 'Mixed operations success rate should be above 85%');
        $this->assertGreaterThan(5, $operationsPerSecond, 'Should handle concurrent mixed operations efficiently');
    }

    private function setupTestData()
    {
        $customers = Customer::factory()->count(200)->create();
        User::factory()->count(50)->create();
        Order::factory()->count(500)->create();
    }
}