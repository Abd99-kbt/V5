<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class LoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // إنشاء بيانات اختبار كبيرة
        $this->seedTestData();
    }

    /** @test */
    public function test_user_authentication_under_load()
    {
        $users = User::factory()->count(100)->create();
        $startTime = microtime(true);
        $successfulAuths = 0;
        $totalAttempts = 500;

        for ($i = 0; $i < $totalAttempts; $i++) {
            $user = $users->random();
            
            $response = $this->post('/login', [
                'username' => $user->username,
                'password' => 'password'
            ]);

            if ($response->status() === 200) {
                $successfulAuths++;
            }
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $requestsPerSecond = $totalAttempts / $duration;

        $this->assertGreaterThan(10, $requestsPerSecond, 'Should handle at least 10 auth requests per second');
        $this->assertGreaterThan(0.8, $successfulAuths / $totalAttempts, 'Success rate should be above 80%');
    }

    /** @test */
    public function test_database_performance_under_load()
    {
        $startTime = microtime(true);
        $queryCount = 1000;

        for ($i = 0; $i < $queryCount; $i++) {
            // محاكاة استعلامات معقدة
            DB::table('orders')
                ->join('customers', 'orders.customer_id', '=', 'customers.id')
                ->join('users', 'orders.user_id', '=', 'users.id')
                ->select('orders.*', 'customers.name as customer_name', 'users.name as user_name')
                ->where('orders.status', 'pending')
                ->limit(10)
                ->get();
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $queriesPerSecond = $queryCount / $duration;

        $this->assertGreaterThan(50, $queriesPerSecond, 'Should execute at least 50 complex queries per second');
        $this->assertLessThan(5, $duration, 'Total execution time should be reasonable');
    }

    /** @test */
    public function test_cache_performance_under_load()
    {
        // إنشاء بيانات للتخزين المؤقت
        $testData = [];
        for ($i = 0; $i < 1000; $i++) {
            $testData["test_key_{$i}"] = "test_value_{$i}";
        }

        // اختبار الكتابة للتخزين المؤقت
        $startTime = microtime(true);
        foreach ($testData as $key => $value) {
            Cache::put($key, $value, 3600);
        }
        $writeTime = microtime(true) - $startTime;

        // اختبار القراءة من التخزين المؤقت
        $startTime = microtime(true);
        $readCount = 0;
        foreach ($testData as $key => $expectedValue) {
            $value = Cache::get($key);
            if ($value === $expectedValue) {
                $readCount++;
            }
        }
        $readTime = microtime(true) - $startTime;

        $writeRate = count($testData) / $writeTime;
        $readRate = count($testData) / $readTime;

        $this->assertGreaterThan(100, $writeRate, 'Cache write rate should be high');
        $this->assertGreaterThan(500, $readRate, 'Cache read rate should be very high');
    }

    /** @test */
    public function test_concurrent_requests_performance()
    {
        $concurrentUsers = 50;
        $requestsPerUser = 10;
        $startTime = microtime(true);

        // محاكاة الطلبات المتزامنة
        $responses = [];
        for ($user = 0; $user < $concurrentUsers; $user++) {
            for ($request = 0; $request < $requestsPerUser; $request++) {
                $response = $this->get('/api/orders?page=1&per_page=25');
                $responses[] = $response->status();
            }
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $totalRequests = $concurrentUsers * $requestsPerUser;
        $requestsPerSecond = $totalRequests / $duration;

        $successfulResponses = array_filter($responses, fn($status) => $status === 200);
        $successRate = count($successfulResponses) / count($responses);

        $this->assertGreaterThan(20, $requestsPerSecond, 'Should handle concurrent requests well');
        $this->assertGreaterThan(0.95, $successRate, 'Success rate should be above 95%');
    }

    /** @test */
    public function test_memory_usage_under_stress()
    {
        $initialMemory = memory_get_usage();
        $iterations = 1000;

        for ($i = 0; $i < $iterations; $i++) {
            // محاكاة عمليات كثيفة في الذاكرة
            $orders = Order::with(['customer', 'user'])->limit(100)->get();
            $userData = $orders->map(function($order) {
                return [
                    'order_id' => $order->id,
                    'customer' => $order->customer->name,
                    'user' => $order->user->name,
                    'total' => $order->total_amount
                ];
            });
            
            unset($orders, $userData);
            
            if ($i % 100 === 0) {
                gc_collect_cycles();
            }
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;
        $memoryIncreaseMB = $memoryIncrease / (1024 * 1024);

        $this->assertLessThan(100, $memoryIncreaseMB, 'Memory increase should be reasonable under stress');
    }

    /** @test */
    public function test_response_time_degradation_under_load()
    {
        $baseResponseTimes = [];
        $loadedResponseTimes = [];

        // قياس أوقات الاستجابة في الحالة الطبيعية
        for ($i = 0; $i < 20; $i++) {
            $startTime = microtime(true);
            $response = $this->get('/admin/dashboard');
            $responseTime = (microtime(true) - $startTime) * 1000;
            $baseResponseTimes[] = $responseTime;
        }

        // إنشاء حمولة
        $loadThreads = [];
        for ($i = 0; $i < 20; $i++) {
            $loadThreads[] = function() {
                for ($j = 0; $j < 10; $j++) {
                    $this->get('/api/orders');
                    usleep(50000); // 50ms delay
                }
            };
        }

        // تنفيذ الحمولة
        foreach ($loadThreads as $thread) {
            $thread();
        }

        // قياس أوقات الاستجابة تحت الحمولة
        for ($i = 0; $i < 20; $i++) {
            $startTime = microtime(true);
            $response = $this->get('/admin/dashboard');
            $responseTime = (microtime(true) - $startTime) * 1000;
            $loadedResponseTimes[] = $responseTime;
        }

        $avgBaseTime = array_sum($baseResponseTimes) / count($baseResponseTimes);
        $avgLoadedTime = array_sum($loadedResponseTimes) / count($loadedResponseTimes);
        $degradationFactor = $avgLoadedTime / $avgBaseTime;

        $this->assertLessThan(3, $degradationFactor, 'Response time degradation should be minimal');
        $this->assertLessThan(1000, $avgLoadedTime, 'Response time should remain under 1 second');
    }

    private function seedTestData()
    {
        // إنشاء بيانات اختبار كبيرة
        $customers = Customer::factory()->count(1000)->create();
        $users = User::factory()->count(100)->create();
        
        Order::factory()
            ->count(5000)
            ->sequence(fn ($sequence) => [
                'customer_id' => $customers->random()->id,
                'user_id' => $users->random()->id,
            ])
            ->create();
    }
}