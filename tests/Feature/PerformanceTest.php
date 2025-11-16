<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_listing_performance()
    {
        // إنشاء بيانات اختبار
        Order::factory()->count(1000)->create();

        $startTime = microtime(true);

        $response = $this->get('/admin/orders');

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);

        // التأكد من أن الاستجابة تستغرق أقل من 500ms
        $this->assertLessThan(500, $executionTime,
            "Orders listing took {$executionTime}ms, expected less than 500ms");
    }

    public function test_cached_order_stats()
    {
        Order::factory()->count(100)->create();

        $startTime = microtime(true);

        $stats = Order::getCachedStats();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // التأكد من وجود الإحصائيات
        $this->assertArrayHasKey('total_orders', $stats);
        $this->assertArrayHasKey('pending_orders', $stats);
        $this->assertArrayHasKey('completed_orders', $stats);

        // التأكد من أن الاستجابة سريعة (أقل من 50ms مع التخزين المؤقت)
        $this->assertLessThan(50, $executionTime,
            "Cached stats took {$executionTime}ms, expected less than 50ms");
    }

    public function test_database_query_optimization()
    {
        Order::factory()->count(500)->create();

        $startTime = microtime(true);

        // استعلام محسن مع eager loading
        $orders = Order::with(['customer:id,name', 'assignedUser:id,name'])
            ->select(['id', 'order_number', 'customer_id', 'status', 'current_stage'])
            ->limit(50)
            ->get();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertCount(50, $orders);

        // التأكد من أن الاستعلام يستغرق أقل من 200ms
        $this->assertLessThan(200, $executionTime,
            "Optimized query took {$executionTime}ms, expected less than 200ms");
    }

    public function test_api_response_performance()
    {
        Order::factory()->count(100)->create();

        $startTime = microtime(true);

        $response = $this->getJson('/api/orders?per_page=25');

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);

        // التأكد من أن API يستجيب في أقل من 300ms
        $this->assertLessThan(300, $executionTime,
            "API response took {$executionTime}ms, expected less than 300ms");
    }
}