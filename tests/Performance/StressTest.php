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

class StressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // إعداد بيانات اختبار كبيرة
        $this->setupLargeTestData();
    }

    /** @test */
    public function test_system_under_extreme_load()
    {
        $concurrentUsers = 100;
        $requestsPerUser = 50;
        $totalRequests = $concurrentUsers * $requestsPerUser;
        
        $startTime = microtime(true);
        $successfulRequests = 0;
        $failedRequests = 0;
        $avgResponseTime = 0;
        $responseTimes = [];
        
        // محاكاة حمولة شديدة
        for ($i = 0; $i < $concurrentUsers; $i++) {
            $user = User::factory()->create();
            $user->assignRole('admin');
            
            for ($j = 0; $j < $requestsPerUser; $j++) {
                $requestStartTime = microtime(true);
                
                try {
                    $this->actingAs($user);
                    
                    // تنويع الطلبات
                    $endpoint = $j % 10;
                    $response = null;
                    
                    switch ($endpoint) {
                        case 0:
                            $response = $this->get('/admin/dashboard');
                            break;
                        case 1:
                            $response = $this->get('/admin/orders');
                            break;
                        case 2:
                            $response = $this->get('/api/orders');
                            break;
                        case 3:
                            $response = $this->get('/api/orders?per_page=50');
                            break;
                        case 4:
                            $customer = Customer::factory()->create();
                            $response = $this->post('/admin/orders', [
                                'customer_id' => $customer->id,
                                'total_amount' => rand(100, 1000) / 100
                            ]);
                            break;
                        case 5:
                            $response = $this->get('/api/customers');
                            break;
                        case 6:
                            $response = $this->get('/api/users');
                            break;
                        case 7:
                            $response = $this->get('/api/orders?status=pending');
                            break;
                        case 8:
                            $response = $this->getJson('/api/orders/summary');
                            break;
                        case 9:
                            $response = $this->get('/admin/orders?search=ORDER');
                            break;
                    }
                    
                    $requestEndTime = microtime(true);
                    $responseTime = ($requestEndTime - $requestStartTime) * 1000;
                    $responseTimes[] = $responseTime;
                    
                    if ($response && $response->status() < 500) {
                        $successfulRequests++;
                    } else {
                        $failedRequests++;
                    }
                    
                } catch (\Exception $e) {
                    $failedRequests++;
                }
                
                // تأخير عشوائي لمحاكاة السلوك الواقعي
                usleep(rand(1000, 5000));
            }
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        
        $successRate = $successfulRequests / $totalRequests;
        $requestsPerSecond = $totalRequests / $totalTime;
        $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
        
        // المعايير المطلوبة تحت الحمولة الشديدة
        $this->assertGreaterThan(0.7, $successRate, 'System should maintain 70% success rate under extreme load');
        $this->assertGreaterThan(20, $requestsPerSecond, 'Should handle 20+ requests per second under stress');
        $this->assertLessThan(2000, $avgResponseTime, 'Average response time should be under 2 seconds under stress');
        
        // تسجيل النتائج
        Log::info('Stress test results', [
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            'success_rate' => $successRate,
            'requests_per_second' => $requestsPerSecond,
            'avg_response_time_ms' => $avgResponseTime,
            'total_time_seconds' => $totalTime
        ]);
    }

    /** @test */
    public function test_memory_under_stress()
    {
        $initialMemory = memory_get_usage(true);
        $initialPeakMemory = memory_get_peak_usage(true);
        
        // إنشاء حمولة ذاكرة مكثفة
        $allocatedData = [];
        $iterations = 1000;
        
        for ($i = 0; $i < $iterations; $i++) {
            // إنشاء بيانات كبيرة
            $dataChunk = str_repeat('X', 10000); // 10KB
            $allocatedData[] = $dataChunk;
            
            // تنفيذ عمليات مكثفة
            Order::factory()->count(10)->create();
            Customer::factory()->count(5)->create();
            
            // عمليات cache مكثفة
            for ($j = 0; $j < 100; $j++) {
                $key = "stress_test_{$i}_{$j}";
                Cache::put($key, $dataChunk, 60);
            }
            
            // استعلامات قاعدة البيانات
            Order::with(['customer', 'user'])->limit(50)->get();
            
            // فحص استخدام الذاكرة كل 100 تكرار
            if ($i % 100 === 0) {
                $currentMemory = memory_get_usage(true);
                $memoryIncrease = $currentMemory - $initialMemory;
                
                $this->assertLessThan(200 * 1024 * 1024, $memoryIncrease, 
                    "Memory usage should not exceed 200MB during stress test at iteration {$i}");
            }
            
            // تنظيف دوري للذاكرة
            if ($i % 50 === 0) {
                unset($dataChunk);
                gc_collect_cycles();
            }
        }
        
        $finalMemory = memory_get_usage(true);
        $finalPeakMemory = memory_get_peak_usage(true);
        
        $memoryIncrease = $finalMemory - $initialMemory;
        $peakMemoryIncrease = $finalPeakMemory - $initialPeakMemory;
        
        $this->assertLessThan(200 * 1024 * 1024, $memoryIncrease, 'Final memory increase should be reasonable');
        $this->assertLessThan(300 * 1024 * 1024, $peakMemoryIncrease, 'Peak memory usage should be controlled');
        
        // تنظيف البيانات الكبيرة
        unset($allocatedData);
    }

    /** @test */
    public function test_database_connection_pool_under_stress()
    {
        $connectionCount = 50;
        $queriesPerConnection = 20;
        $startTime = microtime(true);
        
        $successfulQueries = 0;
        $failedQueries = 0;
        $connectionErrors = 0;
        
        // محاكاة ضغط على pool الاتصالات
        for ($i = 0; $i < $connectionCount; $i++) {
            for ($j = 0; $j < $queriesPerConnection; $j++) {
                try {
                    // استعلامات متنوعة ومكلفة
                    $queryType = $j % 5;
                    
                    switch ($queryType) {
                        case 0:
                            // استعلام معقد مع joins
                            DB::table('orders')
                                ->join('customers', 'orders.customer_id', '=', 'customers.id')
                                ->join('users', 'orders.user_id', '=', 'users.id')
                                ->selectRaw('orders.*, customers.name, users.name as user_name')
                                ->limit(10)
                                ->get();
                            break;
                        case 1:
                            // استعلام مع aggregation
                            DB::table('orders')
                                ->selectRaw('status, COUNT(*) as count, AVG(total_amount) as avg_amount')
                                ->groupBy('status')
                                ->get();
                            break;
                        case 2:
                            // استعلام مع subquery
                            DB::table('orders')
                                ->whereIn('id', function($query) {
                                    $query->select('order_id')
                                        ->from('order_items')
                                        ->limit(10);
                                })
                                ->get();
                            break;
                        case 3:
                            // استعلام مع order و limit
                            Order::orderBy('created_at', 'desc')->limit(20)->get();
                            break;
                        case 4:
                            // استعلام مع where conditions
                            Order::where('status', 'pending')
                                ->where('created_at', '>=', now()->subDays(7))
                                ->count();
                            break;
                    }
                    
                    $successfulQueries++;
                    
                } catch (\Exception $e) {
                    $failedQueries++;
                    if (str_contains($e->getMessage(), 'connection')) {
                        $connectionErrors++;
                    }
                }
                
                usleep(rand(500, 2000)); // تأخير بين الاستعلامات
            }
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalQueries = $connectionCount * $queriesPerConnection;
        $successRate = $successfulQueries / $totalQueries;
        $queriesPerSecond = $totalQueries / $totalTime;
        
        $this->assertGreaterThan(0.9, $successRate, 'Database connection success rate should be above 90%');
        $this->assertLessThan(5, $connectionErrors, 'Connection errors should be minimal');
        $this->assertGreaterThan(10, $queriesPerSecond, 'Should maintain good query throughput');
    }

    /** @test */
    public function test_file_system_under_stress()
    {
        $fileOperations = 200;
        $startTime = microtime(true);
        
        $successfulOperations = 0;
        $failedOperations = 0;
        $filesCreated = [];
        
        for ($i = 0; $i < $fileOperations; $i++) {
            $filename = "stress_test_" . uniqid() . ".txt";
            $content = str_repeat('Test data for file system stress test ', 100);
            
            try {
                $operation = $i % 4;
                
                switch ($operation) {
                    case 0:
                        // كتابة ملف
                        $written = file_put_contents(storage_path("app/public/{$filename}"), $content);
                        if ($written !== false) {
                            $filesCreated[] = $filename;
                            $successfulOperations++;
                        } else {
                            $failedOperations++;
                        }
                        break;
                        
                    case 1:
                        // قراءة ملف
                        if (count($filesCreated) > 0) {
                            $randomFile = $filesCreated[array_rand($filesCreated)];
                            $readContent = file_get_contents(storage_path("app/public/{$randomFile}"));
                            if ($readContent !== false) {
                                $successfulOperations++;
                            } else {
                                $failedOperations++;
                            }
                        } else {
                            $failedOperations++;
                        }
                        break;
                        
                    case 2:
                        // تحديث ملف
                        if (count($filesCreated) > 0) {
                            $randomFile = $filesCreated[array_rand($filesCreated)];
                            $updated = file_put_contents(
                                storage_path("app/public/{$randomFile}"), 
                                $content . " - Updated at " . time()
                            );
                            if ($updated !== false) {
                                $successfulOperations++;
                            } else {
                                $failedOperations++;
                            }
                        } else {
                            $failedOperations++;
                        }
                        break;
                        
                    case 3:
                        // حذف ملف
                        if (count($filesCreated) > 0) {
                            $randomFile = array_pop($filesCreated);
                            $deleted = unlink(storage_path("app/public/{$randomFile}"));
                            if ($deleted) {
                                $successfulOperations++;
                            } else {
                                $failedOperations++;
                            }
                        } else {
                            $failedOperations++;
                        }
                        break;
                }
                
            } catch (\Exception $e) {
                $failedOperations++;
            }
            
            usleep(rand(1000, 5000)); // تأخير بين العمليات
        }
        
        // تنظيف الملفات المتبقية
        foreach ($filesCreated as $file) {
            @unlink(storage_path("app/public/{$file}"));
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $successRate = $successfulOperations / $fileOperations;
        $operationsPerSecond = $fileOperations / $totalTime;
        
        $this->assertGreaterThan(0.8, $successRate, 'File operations success rate should be above 80%');
        $this->assertGreaterThan(1, $operationsPerSecond, 'Should maintain reasonable file operation throughput');
    }

    /** @test */
    public function test_cache_system_under_stress()
    {
        $cacheOperations = 1000;
        $startTime = microtime(true);
        
        $successfulOperations = 0;
        $failedOperations = 0;
        $keysCreated = [];
        
        for ($i = 0; $i < $cacheOperations; $i++) {
            $key = "stress_cache_{$i}";
            $value = str_repeat('Cache data for stress test ', 50);
            $ttl = rand(60, 3600); // 1-60 minutes
            
            try {
                $operation = $i % 5;
                
                switch ($operation) {
                    case 0:
                        // تخزين قيمة
                        $stored = Cache::put($key, $value, $ttl);
                        if ($stored) {
                            $keysCreated[] = $key;
                            $successfulOperations++;
                        } else {
                            $failedOperations++;
                        }
                        break;
                        
                    case 1:
                        // قراءة قيمة
                        $retrieved = Cache::get($key);
                        if ($retrieved !== null) {
                            $successfulOperations++;
                        } else {
                            $failedOperations++;
                        }
                        break;
                        
                    case 2:
                        // تحديث قيمة
                        $updated = Cache::put($key, $value . " - Updated", $ttl);
                        if ($updated) {
                            $successfulOperations++;
                        } else {
                            $failedOperations++;
                        }
                        break;
                        
                    case 3:
                        // حذف قيمة
                        $deleted = Cache::forget($key);
                        if ($deleted) {
                            $keyIndex = array_search($key, $keysCreated);
                            if ($keyIndex !== false) {
                                array_splice($keysCreated, $keyIndex, 1);
                            }
                            $successfulOperations++;
                        } else {
                            $failedOperations++;
                        }
                        break;
                        
                    case 4:
                        // عملية متعددة (increment)
                        $incremented = Cache::increment("counter_{$i}");
                        if ($incremented !== false) {
                            $successfulOperations++;
                        } else {
                            $failedOperations++;
                        }
                        break;
                }
                
            } catch (\Exception $e) {
                $failedOperations++;
            }
            
            usleep(rand(100, 1000)); // تأخير بين العمليات
        }
        
        // تنظيف المفاتيح المتبقية
        foreach ($keysCreated as $key) {
            Cache::forget($key);
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $successRate = $successfulOperations / $cacheOperations;
        $operationsPerSecond = $cacheOperations / $totalTime;
        
        $this->assertGreaterThan(0.9, $successRate, 'Cache operations success rate should be above 90%');
        $this->assertGreaterThan(100, $operationsPerSecond, 'Should maintain high cache operation throughput');
    }

    /** @test */
    public function test_system_recovery_after_stress()
    {
        // المرحلة الأولى: إحداث حمولة شديدة
        $this->generateExtremeLoad();
        
        // المرحلة الثانية: فحص استعادة النظام
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        // فحص استعادة قاعدة البيانات
        $dbResponseStart = microtime(true);
        $orders = Order::count();
        $dbResponseTime = (microtime(true) - $dbResponseStart) * 1000;
        
        $this->assertLessThan(200, $dbResponseTime, 'Database should recover quickly after stress');
        $this->assertGreaterThan(0, $orders, 'Database should be functional');
        
        // فحص استعادة التخزين المؤقت
        $cacheResponseStart = microtime(true);
        Cache::put('recovery_test', 'test value', 60);
        $retrieved = Cache::get('recovery_test');
        $cacheResponseTime = (microtime(true) - $cacheResponseStart) * 1000;
        
        $this->assertLessThan(100, $cacheResponseTime, 'Cache should recover quickly after stress');
        $this->assertEquals('test value', $retrieved, 'Cache should be functional');
        
        // فحص استعادة التطبيق
        $appResponseStart = microtime(true);
        $response = $this->actingAs($user)->get('/admin/dashboard');
        $appResponseTime = (microtime(true) - $appResponseStart) * 1000;
        
        $this->assertLessThan(500, $appResponseTime, 'Application should recover quickly after stress');
        $this->assertEquals(200, $response->status(), 'Application should be responsive');
    }

    private function setupLargeTestData()
    {
        $customers = Customer::factory()->count(2000)->create();
        $users = User::factory()->count(200)->create();
        
        Order::factory()
            ->count(10000)
            ->sequence(fn ($sequence) => [
                'customer_id' => $customers->random()->id,
                'user_id' => $users->random()->id,
                'status' => ['pending', 'processing', 'completed', 'cancelled'][array_rand(['pending', 'processing', 'completed', 'cancelled'])],
                'total_amount' => rand(100, 10000) / 100
            ])
            ->create();
    }

    private function generateExtremeLoad()
    {
        $threads = 20;
        $operationsPerThread = 100;
        
        for ($i = 0; $i < $threads; $i++) {
            for ($j = 0; $j < $operationsPerThread; $j++) {
                // محاكاة عمليات مكثفة
                Order::factory()->create();
                Cache::put("stress_load_{$i}_{$j}", "value", 60);
                usleep(rand(100, 1000));
            }
        }
    }
}