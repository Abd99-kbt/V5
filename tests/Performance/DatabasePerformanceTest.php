<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DatabasePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // إنشاء بيانات اختبار
        $this->seedTestData();
    }

    /** @test */
    public function test_complex_query_performance()
    {
        $startTime = microtime(true);
        
        // محاكاة استعلام معقد مع_join
        $results = DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'orders.id',
                'orders.order_number',
                'orders.status',
                'orders.total_amount',
                'customers.name as customer_name',
                'customers.email as customer_email',
                'users.name as user_name',
                'users.email as user_email',
                DB::raw('COUNT(order_items.id) as items_count'),
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.price * order_items.quantity) as total_items_value')
            )
            ->where('orders.status', 'pending')
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->groupBy('orders.id', 'orders.order_number', 'orders.status', 'orders.total_amount', 
                     'customers.name', 'customers.email', 'users.name', 'users.email')
            ->orderBy('orders.created_at', 'desc')
            ->limit(100)
            ->get();

        $executionTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertGreaterThan(0, $results->count(), 'Should return results');
        $this->assertLessThan(500, $executionTime, 'Complex query should execute within 500ms');
    }

    /** @test */
    public function test_query_optimization_with_indexes()
    {
        // اختبار استعلام بدون index
        $startTime1 = microtime(true);
        $results1 = Order::where('status', 'pending')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();
        $time1 = (microtime(true) - $startTime1) * 1000;

        // اختبار نفس الاستعلام مع index موجود
        DB::statement('CREATE INDEX IF NOT EXISTS idx_orders_status_date ON orders(status, created_at)');
        
        $startTime2 = microtime(true);
        $results2 = Order::where('status', 'pending')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();
        $time2 = (microtime(true) - $startTime2) * 1000;

        $performanceImprovement = $time1 / $time2;
        
        $this->assertGreaterThan(1.5, $performanceImprovement, 'Index should improve query performance');
        $this->assertEquals($results1->count(), $results2->count(), 'Results should be identical');
    }

    /** @test */
    public function test_bulk_insert_performance()
    {
        $bulkData = [];
        $bulkSize = 1000;
        
        $startTime = microtime(true);
        
        DB::beginTransaction();
        try {
            for ($i = 0; $i < $bulkSize; $i++) {
                $bulkData[] = [
                    'order_number' => 'ORDER-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                    'customer_id' => rand(1, 100),
                    'user_id' => rand(1, 50),
                    'status' => 'pending',
                    'total_amount' => rand(100, 1000) / 100,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            
            DB::table('orders')->insert($bulkData);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        
        $executionTime = (microtime(true) - $startTime) * 1000;
        $recordsPerSecond = $bulkSize / ($executionTime / 1000);
        
        $this->assertGreaterThan(1000, $recordsPerSecond, 'Bulk insert should handle 1000+ records per second');
        $this->assertEquals($bulkSize, DB::table('orders')->count(), 'All records should be inserted');
    }

    /** @test */
    public function test_pagination_performance()
    {
        $totalRecords = 10000;
        $pageSize = 25;
        $totalPages = ceil($totalRecords / $pageSize);
        
        $totalQueryTime = 0;
        $totalRecordsFetched = 0;
        
        for ($page = 1; $page <= min(10, $totalPages); $page++) { // اختبار أول 10 صفحات
            $startTime = microtime(true);
            
            $results = Order::with(['customer', 'user'])
                ->orderBy('created_at', 'desc')
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
                ->get();
            
            $queryTime = (microtime(true) - $startTime) * 1000;
            $totalQueryTime += $queryTime;
            $totalRecordsFetched += $results->count();
            
            $this->assertLessThan(100, $queryTime, "Page {$page} should load within 100ms");
        }
        
        $avgQueryTime = $totalQueryTime / min(10, $totalPages);
        
        $this->assertLessThan(50, $avgQueryTime, 'Average pagination query should be fast');
        $this->assertEquals(250, $totalRecordsFetched, 'Should fetch correct number of records');
    }

    /** @test */
    public function test_eager_loading_performance()
    {
        $orderIds = Order::pluck('id')->take(100)->toArray();
        
        // اختبار without eager loading (N+1 problem)
        $startTime1 = microtime(true);
        $orders1 = Order::whereIn('id', $orderIds)->get();
        $customers1 = $orders1->map(function($order) {
            return $order->customer;
        });
        $users1 = $orders1->map(function($order) {
            return $order->user;
        });
        $time1 = (microtime(true) - $startTime1) * 1000;
        
        // اختبار with eager loading
        $startTime2 = microtime(true);
        $orders2 = Order::with(['customer', 'user'])->whereIn('id', $orderIds)->get();
        $customers2 = $orders2->map(function($order) {
            return $order->customer;
        });
        $users2 = $orders2->map(function($order) {
            return $order->user;
        });
        $time2 = (microtime(true) - $startTime2) * 1000;
        
        $performanceImprovement = $time1 / $time2;
        
        $this->assertGreaterThan(2, $performanceImprovement, 'Eager loading should significantly improve performance');
        $this->assertEquals($orders1->count(), $orders2->count(), 'Both methods should return same results');
    }

    /** @test */
    public function test_cache_performance_for_queries()
    {
        $queryKey = 'cached_orders_summary';
        
        // محاكاة استعلام مكلف
        $expensiveQuery = function() {
            return DB::table('orders')
                ->join('customers', 'orders.customer_id', '=', 'customers.id')
                ->select(
                    'orders.status',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(orders.total_amount) as total_value'),
                    DB::raw('AVG(orders.total_amount) as avg_value')
                )
                ->groupBy('orders.status')
                ->get();
        };
        
        // اختبار الأداء بدون cache
        $startTime1 = microtime(true);
        $results1 = $expensiveQuery();
        $time1 = (microtime(true) - $startTime1) * 1000;
        
        // تخزين النتيجة في cache
        Cache::put($queryKey, $results1, 300); // 5 minutes
        
        // اختبار الأداء مع cache
        $startTime2 = microtime(true);
        $results2 = Cache::rememberForever($queryKey, $expensiveQuery);
        $time2 = (microtime(true) - $startTime2) * 1000;
        
        $speedImprovement = $time1 / $time2;
        
        $this->assertGreaterThan(10, $speedImprovement, 'Cache should provide significant speed improvement');
        $this->assertEquals($results1->count(), $results2->count(), 'Cached results should be identical');
    }

    /** @test */
    public function test_concurrent_database_access()
    {
        $user = User::factory()->create();
        $startTime = microtime(true);
        $operations = 100;
        
        $concurrentOperations = function() use ($user, $operations) {
            $successfulOperations = 0;
            
            for ($i = 0; $i < $operations; $i++) {
                try {
                    // محاكاة عمليات قاعدة بيانات متزامنة
                    $order = Order::create([
                        'order_number' => 'CONCURRENT-' . uniqid(),
                        'customer_id' => rand(1, 100),
                        'user_id' => $user->id,
                        'status' => 'pending',
                        'total_amount' => rand(100, 1000) / 100
                    ]);
                    
                    $successfulOperations++;
                } catch (\Exception $e) {
                    // ignore failures in concurrent test
                }
                
                usleep(1000); // 1ms delay between operations
            }
            
            return $successfulOperations;
        };
        
        // تشغيل عدة عمليات متزامنة
        $threads = 5;
        $results = [];
        
        for ($i = 0; $i < $threads; $i++) {
            $results[] = $concurrentOperations();
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalOperations = array_sum($results);
        $operationsPerSecond = $totalOperations / $totalTime;
        
        $this->assertGreaterThan(10, $operationsPerSecond, 'Should handle concurrent database operations');
        $this->assertGreaterThan(50, $totalOperations, 'Most operations should succeed');
    }

    /** @test */
    public function test_large_data_export_performance()
    {
        $startTime = microtime(true);
        
        // محاكاة تصدير البيانات الكبيرة
        $exportData = Order::with(['customer', 'user', 'orderItems'])
            ->where('created_at', '>=', now()->subDays(30))
            ->get()
            ->map(function($order) {
                return [
                    'Order ID' => $order->id,
                    'Order Number' => $order->order_number,
                    'Customer' => $order->customer->name ?? 'N/A',
                    'User' => $order->user->name ?? 'N/A',
                    'Status' => $order->status,
                    'Total Amount' => $order->total_amount,
                    'Items Count' => $order->orderItems->count(),
                    'Created At' => $order->created_at->format('Y-m-d H:i:s')
                ];
            });
        
        $exportTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertLessThan(2000, $exportTime, 'Data export should complete within 2 seconds');
        $this->assertGreaterThan(0, $exportData->count(), 'Should export some data');
    }

    private function seedTestData()
    {
        $customers = Customer::factory()->count(1000)->create();
        $users = User::factory()->count(100)->create();
        
        Order::factory()
            ->count(5000)
            ->sequence(fn ($sequence) => [
                'customer_id' => $customers->random()->id,
                'user_id' => $users->random()->id,
                'status' => ['pending', 'processing', 'completed', 'cancelled'][array_rand(['pending', 'processing', 'completed', 'cancelled'])],
                'total_amount' => rand(100, 10000) / 100
            ])
            ->create();
    }
}