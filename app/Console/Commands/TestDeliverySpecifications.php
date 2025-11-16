<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestDeliverySpecifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-delivery-specifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test delivery specifications functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing delivery specifications functionality...');

        // Test Order model delivery specifications
        $order = \App\Models\Order::first();
        if ($order) {
            $this->info('Order found. Testing delivery specifications:');
            $specs = $order->getDeliverySpecificationsAttribute();
            $this->line('Delivery Specifications: ' . json_encode($specs, JSON_PRETTY_PRINT));

            // Test validation
            $validationErrors = $order->validateDeliverySpecifications();
            if (empty($validationErrors)) {
                $this->info('✓ Delivery specifications validation passed');
            } else {
                $this->error('✗ Delivery specifications validation failed:');
                foreach ($validationErrors as $error) {
                    $this->error('  - ' . $error);
                }
            }
        } else {
            $this->warn('No orders found to test');
        }

        // Test SpecificationValidationService
        $service = app(\App\Services\SpecificationValidationService::class);
        $this->info('✓ SpecificationValidationService instantiated successfully');

        // Test OrderProcessing delivery specifications
        $processing = \App\Models\OrderProcessing::first();
        if ($processing) {
            $this->info('Testing OrderProcessing delivery specifications:');
            $stageErrors = $processing->validateDeliverySpecificationsForStage();
            if (empty($stageErrors)) {
                $this->info('✓ OrderProcessing delivery specifications validation passed');
            } else {
                $this->warn('OrderProcessing delivery specifications validation warnings:');
                foreach ($stageErrors as $error) {
                    $this->warn('  - ' . $error);
                }
            }
        } else {
            $this->warn('No order processing records found to test');
        }

        $this->info('Delivery specifications testing completed!');
    }
}
