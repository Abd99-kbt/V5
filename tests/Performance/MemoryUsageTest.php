<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MemoryUsageTest extends TestCase
{
    use RefreshDatabase;

    private $largeDataset;

    /** @test */
    public function test_memory_usage_for_large_datasets()
    {
        $initialMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        // إنشاء مجموعة كبيرة من البيانات
        $this->createLargeDataset();

        $afterDataCreation = memory_get_usage(true);
        $memoryUsedForData = $afterDataCreation - $initialMemory;

        // تنظيف الذاكرة
        unset($this->largeDataset);
        gc_collect_cycles();

        $afterCleanup = memory_get_usage(true);
        $remainingMemoryUsage = $afterCleanup - $initialMemory;

        $this->assertLessThan(100 * 1024 * 1024, $memoryUsedForData, 'Memory usage for large dataset should be reasonable');
        $this->assertLessThan(10 * 1024 * 1024, $remainingMemoryUsage, 'Memory should be properly released after cleanup');
    }

    /** @test */
    public function test_memory_leaks_in_eloquent_queries()
    {
        gc_collect_cycles();
        $initialMemory = memory_get_usage();

        $user = User::factory()->create();
        $orders = Order::factory()->count(100)->create(['user_id' => $user->id]);

        // تنفيذ استعلامات متكررة محتملة للتسريب
        for ($i = 0; $i < 1000; $i++) {
            $order = Order::find(rand(1, $orders->count()));
            if ($order) {
                $order->load('customer', 'user');
            }
            unset($order);
            
            if ($i % 100 === 0) {
                gc_collect_cycles();
                $memoryAtCheckpoint = memory_get_usage();
                $this->assertLessThan(50 * 1024 * 1024, 
                    $memoryAtCheckpoint - $initialMemory, 
                    "Memory leak detected at iteration {$i}"
                );
            }
        }
    }

    /** @test */
    public function test_memory_efficiency_of_chunking()
    {
        $totalOrders = 10000;
        $chunkSize = 100;
        
        $orders = Order::factory()->count($totalOrders)->create();
        
        gc_collect_cycles();
        $initialMemory = memory_get_usage();

        $totalProcessed = 0;
        Order::chunk($chunkSize, function ($orderChunk) use (&$totalProcessed) {
            foreach ($orderChunk as $order) {
                $totalProcessed++;
                // محاكاة معالجة البيانات
                $data = [
                    'id' => $order->id,
                    'customer_name' => $order->customer->name ?? '',
                    'user_name' => $order->user->name ?? '',
                    'total' => $order->total_amount
                ];
                unset($data); // تنظيف فوري
            }
            // فرض تنظيف الذاكرة في نهاية كل chunk
            unset($orderChunk);
            gc_collect_cycles();
        });

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        $this->assertEquals($totalOrders, $totalProcessed, 'All orders should be processed');
        $this->assertLessThan(20 * 1024 * 1024, $memoryIncrease, 'Chunking should use memory efficiently');
    }

    /** @test */
    public function test_memory_usage_with_eager_loading()
    {
        $orders = Order::factory()->count(1000)->create();
        
        gc_collect_cycles();
        $memoryWithoutEagerLoading = memory_get_usage();

        // استعلام بدون eager loading
        $ordersWithoutEagerLoad = Order::all();
        foreach ($ordersWithoutEagerLoad as $order) {
            $customerName = $order->customer->name ?? '';
            $userName = $order->user->name ?? '';
        }

        unset($ordersWithoutEagerLoad);
        gc_collect_cycles();
        $memoryAfterWithoutEagerLoad = memory_get_usage();

        // استعلام مع eager loading
        $ordersWithEagerLoad = Order::with(['customer', 'user'])->get();
        foreach ($ordersWithEagerLoad as $order) {
            $customerName = $order->customer->name ?? '';
            $userName = $order->user->name ?? '';
        }

        unset($ordersWithEagerLoad);
        gc_collect_cycles();
        $memoryAfterWithEagerLoad = memory_get_usage();

        $memoryWithoutEagerLoadIncrease = $memoryAfterWithoutEagerLoad - $memoryWithoutEagerLoading;
        $memoryWithEagerLoadIncrease = $memoryAfterWithEagerLoad - $memoryAfterWithoutEagerLoad;

        $this->assertLessThan($memoryWithoutEagerLoadIncrease, $memoryWithEagerLoadIncrease, 
            'Eager loading should use more memory but be more efficient overall');
    }

    /** @test */
    public function test_memory_usage_during_mass_operations()
    {
        $initialMemory = memory_get_usage(true);
        
        // محاكاة عملية ضخمة (تصدير بيانات)
        $batchSize = 1000;
        $totalRecords = 50000;
        $exportData = [];
        
        for ($offset = 0; $offset < $totalRecords; $offset += $batchSize) {
            $batch = Order::offset($offset)
                ->limit($batchSize)
                ->with(['customer', 'user'])
                ->get()
                ->map(function($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer' => $order->customer->name ?? '',
                        'user' => $order->user->name ?? '',
                        'status' => $order->status,
                        'total_amount' => $order->total_amount,
                        'created_at' => $order->created_at
                    ];
                });
            
            // إضافة البيانات للتصدير
            foreach ($batch as $record) {
                $exportData[] = $record;
            }
            
            unset($batch);
            unset($record);
            gc_collect_cycles();
            
            // فحص استخدام الذاكرة
            $currentMemory = memory_get_usage(true);
            $memoryIncrease = $currentMemory - $initialMemory;
            $this->assertLessThan(100 * 1024 * 1024, $memoryIncrease, 
                "Memory usage should not exceed 100MB during mass operations");
        }

        $finalMemory = memory_get_usage(true);
        $finalMemoryIncrease = $finalMemory - $initialMemory;

        $this->assertEquals($totalRecords, count($exportData), 'All records should be processed');
        $this->assertLessThan(100 * 1024 * 1024, $finalMemoryIncrease, 'Final memory usage should be reasonable');
        
        unset($exportData);
    }

    /** @test */
    public function test_cache_memory_efficiency()
    {
        $testDataSize = 1000;
        $cachePrefix = 'memory_test_';
        
        gc_collect_cycles();
        $initialMemory = memory_get_usage();
        
        // إنشاء بيانات للاختبار
        $testData = [];
        for ($i = 0; $i < $testDataSize; $i++) {
            $testData["{$cachePrefix}{$i}"] = str_repeat('data', 100); // ~400 bytes each
        }
        
        // تخزين البيانات في الذاكرة المؤقتة
        foreach ($testData as $key => $value) {
            Cache::put($key, $value, 3600);
        }
        
        $afterCacheWrite = memory_get_usage();
        $cacheWriteMemoryIncrease = $afterCacheWrite - $initialMemory;
        
        // قراءة البيانات من الذاكرة المؤقتة
        $readData = [];
        foreach ($testData as $key => $value) {
            $readData[$key] = Cache::get($key);
        }
        
        $afterCacheRead = memory_get_usage();
        $cacheReadMemoryIncrease = $afterCacheRead - $initialMemory;
        
        // تنظيف الذاكرة المؤقتة
        foreach ($testData as $key => $value) {
            Cache::forget($key);
        }
        
        $afterCacheCleanup = memory_get_usage();
        $cacheCleanupMemoryIncrease = $afterCacheCleanup - $initialMemory;
        
        $this->assertLessThan(100 * 1024 * 1024, $cacheWriteMemoryIncrease, 'Cache write should not use excessive memory');
        $this->assertLessThan(100 * 1024 * 1024, $cacheReadMemoryIncrease, 'Cache read should not use excessive memory');
        $this->assertLessThan(10 * 1024 * 1024, $cacheCleanupMemoryIncrease, 'Memory should be released after cache cleanup');
    }

    private function createLargeDataset()
    {
        $customers = Customer::factory()->count(1000)->create();
        $users = User::factory()->count(100)->create();
        
        $this->largeDataset = Order::factory()
            ->count(5000)
            ->sequence(fn ($sequence) => [
                'customer_id' => $customers->random()->id,
                'user_id' => $users->random()->id,
            ])
            ->create();
    }
}