<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class DatabaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_database_cache_integration()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        
        // Test database operation with caching
        Cache::put('test_key', 'test_value', 60);
        
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $customer->id
        ]);
        
        $cachedValue = Cache::get('test_key');
        $this->assertEquals('test_value', $cachedValue);
        
        $retrievedOrder = Order::find($order->id);
        $this->assertNotNull($retrievedOrder);
    }

    /** @test */
    public function test_queue_database_integration()
    {
        Queue::fake();
        
        $user = User::factory()->create();
        
        // Simulate queue job with database operation
        $order = Order::factory()->create(['user_id' => $user->id]);
        
        Queue::assertNothingPushed();
    }
}