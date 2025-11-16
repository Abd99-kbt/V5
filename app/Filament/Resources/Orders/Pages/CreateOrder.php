<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Services\CustomerValidationService;
use App\Services\OrderProcessingService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['order_number'] = Order::generateOrderNumber();

        // Set created_by if not set
        if (!isset($data['created_by'])) {
            $data['created_by'] = \Illuminate\Support\Facades\Auth::id();
        }

        // Calculate and set pricing if pricing fields are provided
        if (isset($data['price_per_ton']) && isset($data['required_weight'])) {
            $tempOrder = new Order($data);
            $orderProcessingService = app(OrderProcessingService::class);
            $calculation = $orderProcessingService->calculateOrderPricing($tempOrder);

            if ($calculation['is_valid']) {
                $data['estimated_price'] = $calculation['total_amount'];
                $data['final_price'] = $calculation['total_amount'];
                $data['pricing_breakdown'] = $calculation['breakdown'];
                $data['pricing_calculated'] = true;
                $data['pricing_calculated_at'] = now();
                $data['pricing_calculated_by'] = \Illuminate\Support\Facades\Auth::id();
            }
        }

        return $data;
    }

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();

        // Validate pricing inputs
        $tempOrder = new Order($data);
        $orderProcessingService = app(OrderProcessingService::class);
        $pricingValidation = $orderProcessingService->validatePricingInputs($tempOrder);

        if (!$pricingValidation['is_valid']) {
            Notification::make()
                ->title(__('orders.pricing_validation_error'))
                ->body(__('orders.pricing_validation_error_body') . ' ' . implode(', ', $pricingValidation['errors']))
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
            return;
        }

        // Validate customer if selected
        if (!empty($data['customer_id'])) {
            $customer = Customer::find($data['customer_id']);
            $orderAmount = $data['total_amount'] ?? 0;

            if ($customer && $orderAmount > 0) {
                $validationService = new CustomerValidationService();
                $validation = $validationService->validateCustomerForOrder($customer, $orderAmount);

                if (!$validation['valid']) {
                    // Show blocking error
                    Notification::make()
                        ->title(__('orders.cannot_create_order'))
                        ->body($validation['validations']['credit']['message'])
                        ->danger()
                        ->persistent()
                        ->send();

                    $this->halt();
                    return;
                }

                // Show warnings if any
                if (!empty($validation['warnings'])) {
                    foreach ($validation['warnings'] as $warning) {
                        Notification::make()
                            ->title(__('orders.customer_warning'))
                            ->body($warning)
                            ->warning()
                            ->send();
                    }
                }
            }
        }
    }
}
