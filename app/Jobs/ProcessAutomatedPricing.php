<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\AutomatedPricingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAutomatedPricing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $user;

    /**
     * Create a new job instance.
     */
    public function __construct(Order $order, $user = null)
    {
        $this->order = $order;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $pricingService = new AutomatedPricingService();
            $result = $pricingService->calculateComprehensivePrice($this->order, $this->user);

            // Log result
            Log::info('Automated pricing completed via job', [
                'order_id' => $this->order->id,
                'success' => $result['success'],
                'total_price' => $result['total_price'] ?? null,
                'execution_time' => now()->diffInMilliseconds($this->order->updated_at)
            ]);

            // Invalidate related caches
            \App\Services\CacheManager::invalidatePattern("pricing.{$this->order->id}");
            \App\Services\CacheManager::invalidatePattern("orders.stats");

        } catch (\Exception $e) {
            Log::error('Automated pricing job failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark job as failed
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error('Automated pricing job permanently failed', [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage()
        ]);

        // You could send notifications here
        // Notification::send($this->user, new PricingFailedNotification($this->order, $exception));
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'pricing',
            'order:' . $this->order->id,
            'user:' . ($this->user ? $this->user->id : 'system')
        ];
    }
}