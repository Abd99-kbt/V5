<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Order;
use App\Filament\Components\CustomerSelect;
use App\Services\CustomerValidationService;
use App\Services\OrderProcessingService;

class OrderForm
{
    /**
     * Update pricing calculation when form fields change
     */
    private static function updatePricingCalculation($changedValue, callable $get, callable $set, OrderProcessingService $orderProcessingService): void
    {
        $pricePerTon = $get('price_per_ton') ?? 0;
        $requiredWeight = $get('required_weight') ?? 0;
        $cuttingFees = $get('cutting_fees') ?? 0;
        $discount = $get('discount') ?? 0;

        // Create a temporary order object for calculation
        $tempOrder = new Order([
            'price_per_ton' => $pricePerTon,
            'required_weight' => $requiredWeight,
            'cutting_fees' => $cuttingFees,
            'discount' => $discount,
        ]);

        // Validate inputs
        $validation = $orderProcessingService->validatePricingInputs($tempOrder);
        if (!$validation['is_valid']) {
            // Don't update if validation fails
            return;
        }

        // Calculate pricing
        $calculation = $orderProcessingService->calculateOrderPricing($tempOrder);

        if ($calculation['is_valid']) {
            $set('estimated_price', $calculation['total_amount']);
            $set('final_price', $calculation['total_amount']);
        }
    }

    public static function configure(Schema $schema): Schema
    {
        $orderProcessingService = app(OrderProcessingService::class);

        return $schema
            ->components([
                Placeholder::make('order_number_preview')
                    ->label(__('orders.order_number'))
                    ->content(fn () => Order::generateOrderNumber())
                    ->columnSpanFull(),
                TextInput::make('order_number')
                    ->label(__('orders.order_number'))
                    ->disabled()
                    ->dehydrated(false)
                    ->hidden(),
                Select::make('type')
                    ->label(__('orders.type'))
                    ->options(__('orders.type_options'))
                    ->default('out')
                    ->required(),
                Select::make('status')
                    ->label(__('orders.status'))
                    ->options(__('orders.status_options'))
                    ->default('pending')
                    ->required(),
                TextInput::make('customer_name')
                    ->label(__('orders.customer_name'))
                    ->default(null)
                    ->datalist(function () {
                        return Customer::where('is_active', true)
                            ->whereNotNull('name_en')
                            ->distinct()
                            ->pluck('name_en')
                            ->take(10)
                            ->toArray();
                    }),
                TextInput::make('customer_phone')
                    ->label(__('orders.customer_phone'))
                    ->tel()
                    ->default(null)
                    ->datalist(function () {
                        return Customer::where('is_active', true)
                            ->whereNotNull('mobile_number')
                            ->distinct()
                            ->pluck('mobile_number')
                            ->take(10)
                            ->toArray();
                    }),
                TextInput::make('customer_email')
                    ->label(__('orders.customer_email'))
                    ->email()
                    ->default(null)
                    ->datalist(function () {
                        return Customer::where('is_active', true)
                            ->whereNotNull('email')
                            ->distinct()
                            ->pluck('email')
                            ->take(10)
                            ->toArray();
                    }),
                TextInput::make('customer_location')
                    ->label(__('orders.customer_location'))
                    ->default(null)
                    ->datalist(function () {
                        return Customer::where('is_active', true)
                            ->whereNotNull('customer_location')
                            ->distinct()
                            ->pluck('customer_location')
                            ->take(10)
                            ->toArray();
                    }),
                Textarea::make('customer_address')
                    ->label(__('orders.customer_address'))
                    ->default(null)
                    ->columnSpanFull(),
                DatePicker::make('order_date')
                    ->label(__('orders.order_date'))
                    ->required(),
                DatePicker::make('required_date')
                    ->label(__('orders.required_date')),
                DatePicker::make('shipped_date')
                    ->label(__('orders.shipped_date')),
                TextInput::make('subtotal')
                    ->label(__('orders.subtotal'))
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('tax_amount')
                    ->label(__('orders.tax_amount'))
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('discount_amount')
                    ->label(__('orders.discount_amount'))
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('shipping_cost')
                    ->label(__('orders.shipping_cost'))
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('total_amount')
                    ->label(__('orders.total_amount'))
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Textarea::make('notes')
                    ->label(__('orders.notes'))
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('tracking_number')
                    ->label(__('orders.tracking_number'))
                    ->default(null),
                Toggle::make('is_paid')
                    ->label(__('orders.is_paid'))
                    ->required(),
                DateTimePicker::make('paid_at')
                    ->label(__('orders.paid_at')),
                CustomerSelect::make('customer_id')
                    ->label(__('orders.customer_id'))
                    ->default(null)
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if ($state) {
                            $customer = Customer::find($state);
                            if ($customer) {
                                // Auto-fill customer details
                                $set('customer_name', $customer->name);
                                $set('customer_phone', $customer->mobile_number);
                                $set('customer_email', $customer->email);
                                $set('customer_location', $customer->customer_location);
                                $set('customer_address', $customer->address);

                                // Validate credit limit
                                $orderAmount = $get('total_amount') ?? 0;
                                if ($orderAmount > 0) {
                                    $validationService = new CustomerValidationService();
                                    $validation = $validationService->validateCustomerForOrder($customer, $orderAmount);

                                    if (!$validation['valid']) {
                                        // Show notification warning
                                        \Filament\Notifications\Notification::make()
                                            ->title(__('orders.credit_limit_warning'))
                                            ->body($validation['validations']['credit']['message'])
                                            ->warning()
                                            ->send();
                                    }
                                }
                            }
                        } else {
                            // Clear customer details when no customer selected
                            $set('customer_name', null);
                            $set('customer_phone', null);
                            $set('customer_email', null);
                            $set('customer_location', null);
                            $set('customer_address', null);
                        }
                    }),
                TextInput::make('created_by')
                    ->label(__('orders.created_by'))
                    ->numeric()
                    ->default(null),
                TextInput::make('assigned_to')
                    ->label(__('orders.assigned_to'))
                    ->numeric()
                    ->default(null),
                Select::make('current_stage')
                    ->label(__('orders.current_stage'))
                    ->options(__('orders.current_stage_options'))
                    ->default('إنشاء')
                    ->required(),
                TextInput::make('required_weight')
                    ->label(__('orders.required_weight'))
                    ->numeric()
                    ->default(null),
                TextInput::make('required_length')
                    ->label(__('orders.required_length'))
                    ->numeric()
                    ->default(null),
                TextInput::make('required_width')
                    ->label(__('orders.required_width'))
                    ->numeric()
                    ->default(null),
                TextInput::make('required_plates')
                    ->label(__('orders.required_plates'))
                    ->numeric()
                    ->default(null),
                Select::make('material_type')
                    ->label(__('orders.material_type'))
                    ->options(__('orders.material_type_options'))
                    ->default(null),
                Select::make('delivery_method')
                    ->label(__('orders.delivery_method'))
                    ->options(__('orders.delivery_method_options'))
                    ->default('استلام_ذاتي')
                    ->required(),
                TextInput::make('estimated_price')
                    ->label(__('orders.estimated_price'))
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('final_price')
                    ->label(__('orders.final_price'))
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('paid_amount')
                    ->label(__('orders.paid_amount'))
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('remaining_amount')
                    ->label(__('orders.remaining_amount'))
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('discount')
                    ->label(__('orders.discount'))
                    ->required()
                    ->numeric()
                    ->default(0.0),
                // Pricing Section
                Section::make(__('orders.pricing_information'))
                    ->description(__('orders.pricing_description'))
                    ->schema([
                        TextInput::make('price_per_ton')
                            ->label(__('orders.price_per_ton'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->placeholder(__('orders.enter_price_per_ton'))
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) use ($orderProcessingService) {
                                self::updatePricingCalculation($state, $get, $set, $orderProcessingService);
                            })
                            ->rules(['required', 'numeric', 'min:0']),
                        TextInput::make('cutting_fees')
                            ->label(__('orders.cutting_fees'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->placeholder(__('orders.enter_cutting_fees'))
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) use ($orderProcessingService) {
                                self::updatePricingCalculation($state, $get, $set, $orderProcessingService);
                            })
                            ->rules(['numeric', 'min:0']),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                DateTimePicker::make('submitted_at')
                    ->label(__('orders.submitted_at')),
                DateTimePicker::make('approved_at')
                    ->label(__('orders.approved_at')),
                DateTimePicker::make('started_at')
                    ->label(__('orders.started_at')),
                DateTimePicker::make('completed_at')
                    ->label(__('orders.completed_at')),
                Textarea::make('specifications')
                    ->label(__('orders.specifications'))
                    ->default(null)
                    ->columnSpanFull(),
                // Delivery Specifications Section
                Section::make(__('orders.delivery_specifications'))
                    ->description(__('orders.delivery_specifications_description'))
                    ->schema([
                        TextInput::make('delivery_width')
                            ->label(__('orders.width_cm'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->placeholder(__('orders.enter_width_cm')),
                        TextInput::make('delivery_length')
                            ->label(__('orders.length_cm'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->placeholder(__('orders.enter_length_cm')),
                        TextInput::make('delivery_thickness')
                            ->label(__('orders.thickness_mm'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->placeholder(__('orders.enter_thickness_mm')),
                        TextInput::make('delivery_grammage')
                            ->label(__('orders.grammage'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->placeholder(__('orders.enter_grammage')),
                        TextInput::make('delivery_quality')
                            ->label(__('orders.quality_grade'))
                            ->placeholder(__('orders.enter_quality_grade')),
                        TextInput::make('delivery_quantity')
                            ->label(__('orders.quantity_pieces'))
                            ->numeric()
                            ->minValue(0)
                            ->placeholder(__('orders.enter_required_quantity')),
                        TextInput::make('delivery_weight')
                            ->label(__('orders.weight_kg'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->placeholder(__('orders.enter_required_weight')),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Toggle::make('is_urgent')
                    ->label(__('orders.is_urgent'))
                    ->required(),
            ]);
    }
}
