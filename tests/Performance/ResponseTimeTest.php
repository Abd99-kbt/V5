<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ResponseTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // إنشاء بيانات اختبار
        $customers = Customer::factory()->count(1000)->create();
        $users = User::factory()->count(100)->create();
        
        Order::factory()
            ->count(3000)
            ->sequence(fn ($sequence) => [
                'customer_id' => $customers->random()->id,
                'user_id' => $users->random()->id,
            ])
            ->create();
    }

    /** @test */
    public function test_homepage_response_time()
    {
        $startTime = microtime(true);
        
        $response = $this->get('/');
        
        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        
        // homepage يجب أن يكون سريع جداً
        $this->assertLessThan(200, $responseTime, 'Homepage response time should be under 200ms');
    }

    /** @test */
    public function test_admin_dashboard_response_time()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        $startTime = microtime(true);
        
        $response = $this->actingAs($user)->get('/admin/dashboard');
        
        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        
        // dashboard قد يكون أبطأ قليلاً من homepage
        $this->assertLessThan(500, $responseTime, 'Dashboard response time should be under 500ms');
    }

    /** @test */
    public function test_orders_list_response_time()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        $startTime = microtime(true);
        
        $response = $this->actingAs($user)->get('/admin/orders');
        
        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        
        // orders list مع بيانات كثيرة يجب أن يكون تحت 800ms
        $this->assertLessThan(800, $responseTime, 'Orders list response time should be under 800ms');
    }

    /** @test */
    public function test_search_functionality_response_time()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        $searchTerms = [
            'ORDER',
            'CUSTOMER',
            'PENDING',
            'COMPLETED'
        ];
        
        $totalResponseTime = 0;
        $successfulSearches = 0;
        
        foreach ($searchTerms as $term) {
            $startTime = microtime(true);
            
            $response = $this->actingAs($user)
                ->get('/admin/orders?search=' . $term);
            
            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000;
            
            if ($response->status() === 200) {
                $totalResponseTime += $responseTime;
                $successfulSearches++;
            }
            
            // كل بحث فردي يجب أن يكون سريع
            $this->assertLessThan(300, $responseTime, "Search for '{$term}' should be under 300ms");
        }
        
        $avgResponseTime = $totalResponseTime / $successfulSearches;
        $this->assertLessThan(250, $avgResponseTime, 'Average search response time should be under 250ms');
    }

    /** @test */
    public function test_order_creation_response_time()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        $customer = Customer::factory()->create();
        
        $orderData = [
            'customer_id' => $customer->id,
            'total_amount' => 150.00,
            'status' => 'pending'
        ];
        
        $startTime = microtime(true);
        
        $response = $this->actingAs($user)
            ->post('/admin/orders', $orderData);
        
        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(201);
        
        // إنشاء order يجب أن يكون سريع
        $this->assertLessThan(300, $responseTime, 'Order creation should be under 300ms');
    }

    /** @test */
    public function test_data_export_response_time()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        $startTime = microtime(true);
        
        $response = $this->actingAs($user)
            ->get('/admin/orders/export?format=csv');
        
        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        
        // تصدير البيانات قد يكون أبطأ
        $this->assertLessThan(2000, $responseTime, 'Data export should be under 2 seconds');
    }

    /** @test */
    public function test_pagination_response_time()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        $pagesToTest = [1, 2, 3, 10];
        $totalResponseTime = 0;
        $successfulPages = 0;
        
        foreach ($pagesToTest as $page) {
            $startTime = microtime(true);
            
            $response = $this->actingAs($user)
                ->get("/admin/orders?page={$page}&per_page=25");
            
            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000;
            
            if ($response->status() === 200) {
                $totalResponseTime += $responseTime;
                $successfulPages++;
            }
            
            // كل صفحة يجب أن تكون سريعة
            $this->assertLessThan(400, $responseTime, "Page {$page} should be under 400ms");
        }
        
        $avgResponseTime = $totalResponseTime / $successfulPages;
        $this->assertLessThan(350, $avgResponseTime, 'Average pagination response time should be under 350ms');
    }

    /** @test */
    public function test_user_profile_loading_time()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        // تسجيل الدخول أولاً
        $loginStartTime = microtime(true);
        $this->actingAs($user)->get('/admin');
        $loginEndTime = microtime(true);
        $loginTime = ($loginEndTime - $loginStartTime) * 1000;
        
        // تحميل صفحة الملف الشخصي
        $profileStartTime = microtime(true);
        $response = $this->actingAs($user)->get('/admin/profile');
        $profileEndTime = microtime(true);
        $profileTime = ($profileEndTime - $profileStartTime) * 1000;
        
        $response->assertStatus(200);
        
        $this->assertLessThan(500, $loginTime, 'Login should be under 500ms');
        $this->assertLessThan(400, $profileTime, 'Profile loading should be under 400ms');
    }

    /** @test */
    public function test_api_endpoints_response_time()
    {
        $apiEndpoints = [
            ['method' => 'GET', 'url' => '/api/orders'],
            ['method' => 'GET', 'url' => '/api/orders/1'],
            ['method' => 'POST', 'url' => '/api/orders'],
            ['method' => 'PUT', 'url' => '/api/orders/1'],
            ['method' => 'DELETE', 'url' => '/api/orders/1']
        ];
        
        foreach ($apiEndpoints as $endpoint) {
            $startTime = microtime(true);
            
            switch ($endpoint['method']) {
                case 'GET':
                    $response = $this->getJson($endpoint['url']);
                    break;
                case 'POST':
                    $response = $this->postJson($endpoint['url'], [
                        'customer_id' => 1,
                        'total_amount' => 100.00
                    ]);
                    break;
                case 'PUT':
                    $response = $this->putJson($endpoint['url'], [
                        'status' => 'processing'
                    ]);
                    break;
                case 'DELETE':
                    $response = $this->deleteJson($endpoint['url']);
                    break;
            }
            
            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000;
            
            // API endpoints يجب أن تكون سريعة جداً
            $this->assertLessThan(200, $responseTime, 
                "{$endpoint['method']} {$endpoint['url']} should be under 200ms");
        }
    }

    /** @test */
    public function test_response_time_under_heavy_load()
    {
        $concurrentUsers = 20;
        $requestsPerUser = 5;
        
        $baseResponseTimes = [];
        $loadedResponseTimes = [];
        
        // قياس أوقات الاستجابة في الحالة الطبيعية
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);
            $response = $this->get('/admin/orders');
            $responseTime = (microtime(true) - $startTime) * 1000;
            $baseResponseTimes[] = $responseTime;
        }
        
        // إنشاء حمولة متزامنة
        $loadThreads = [];
        for ($i = 0; $i < $concurrentUsers; $i++) {
            $loadThreads[] = function() use ($requestsPerUser) {
                for ($j = 0; $j < $requestsPerUser; $j++) {
                    $this->get('/api/orders');
                    usleep(10000); // 10ms delay between requests
                }
            };
        }
        
        // تنفيذ الحمولة
        foreach ($loadThreads as $thread) {
            $thread();
        }
        
        // قياس أوقات الاستجابة تحت الحمولة
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);
            $response = $this->get('/admin/orders');
            $responseTime = (microtime(true) - $startTime) * 1000;
            $loadedResponseTimes[] = $responseTime;
        }
        
        $avgBaseTime = array_sum($baseResponseTimes) / count($baseResponseTimes);
        $avgLoadedTime = array_sum($loadedResponseTimes) / count($loadedResponseTimes);
        
        $degradationFactor = $avgLoadedTime / $avgBaseTime;
        
        $this->assertLessThan(2.5, $degradationFactor, 
            'Response time degradation should be minimal under load');
        $this->assertLessThan(1000, $avgLoadedTime, 
            'Response time should remain reasonable even under load');
    }

    /** @test */
    public function test_database_intensive_operations_response_time()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        $startTime = microtime(true);
        
        // عمليات كثيفة على قاعدة البيانات
        $response = $this->actingAs($user)->get('/admin/reports/summary');
        
        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        
        // عمليات التقارير قد تكون أبطأ
        $this->assertLessThan(1500, $responseTime, 
            'Database intensive operations should complete within 1.5 seconds');
    }
}