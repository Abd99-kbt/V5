<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderStage;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::all();
        $products = Product::all();
        $users = User::all();
        $warehouses = \App\Models\Warehouse::all();

        if ($customers->isEmpty() || $products->isEmpty() || $users->isEmpty() || $warehouses->isEmpty()) {
            return;
        }

        // Create sample orders
        $orders = [
            [
                'order_number' => 'ORD-' . str_pad(1, 6, '0', STR_PAD_LEFT),
                'warehouse_id' => $warehouses->first()->id,
                'customer_id' => $customers->first()->id,
                'created_by' => $users->first()->id,
                'assigned_to' => $users->skip(1)->first()?->id,
                'material_type' => 'كرتون',
                'required_weight' => 2500.00,
                'estimated_price' => 3750.00,
                'delivery_method' => 'استلام_ذاتي',
                'notes' => 'طلب كرتون مقوى للطباعة',
                'is_urgent' => false,
                'order_date' => now()->toDateString(),
                'materials' => [
                    [
                        'material_id' => $products->where('type', 'كرتون')->first()?->id ?? $products->first()->id,
                        'requested_weight' => 2500.00,
                        'selling_price_per_ton' => 1500.00,
                        'notes' => 'كرتون عالي الجودة مطلوب',
                    ]
                ]
            ],
            [
                'order_number' => 'ORD-' . str_pad(2, 6, '0', STR_PAD_LEFT),
                'warehouse_id' => $warehouses->first()->id,
                'customer_id' => $customers->skip(1)->first()?->id ?? $customers->first()->id,
                'created_by' => $users->first()->id,
                'assigned_to' => $users->skip(2)->first()?->id,
                'material_type' => 'ورق',
                'required_weight' => 1500.00,
                'estimated_price' => 2100.00,
                'delivery_method' => 'توصيل',
                'notes' => 'طلب ورق كرافت للتغليف',
                'is_urgent' => true,
                'order_date' => now()->toDateString(),
                'materials' => [
                    [
                        'material_id' => $products->where('type', 'ورق')->first()?->id ?? $products->first()->id,
                        'requested_weight' => 1500.00,
                        'selling_price_per_ton' => 1400.00,
                        'notes' => 'ورق كرافت بني مطلوب',
                    ]
                ]
            ],
        ];

        foreach ($orders as $orderData) {
            $materials = $orderData['materials'];
            unset($orderData['materials']);

            $order = Order::create($orderData);

            // Add materials to order via order items
            foreach ($materials as $materialData) {
                \App\Models\OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $materialData['material_id'],
                    'quantity' => $materialData['requested_weight'],
                    'unit_price' => $materialData['selling_price_per_ton'],
                    'total_price' => $materialData['requested_weight'] * $materialData['selling_price_per_ton'],
                    'notes' => $materialData['notes'],
                ]);
            }

            // Create order stages using OrderStage model
            $stages = [
                [
                    'stage_name' => 'إنشاء',
                    'stage_order' => 1,
                    'status' => 'مكتمل',
                    'requires_approval' => false,
                ],
                [
                    'stage_name' => 'مراجعة',
                    'stage_order' => 2,
                    'status' => 'معلق',
                    'requires_approval' => true,
                ],
                [
                    'stage_name' => 'حجز_المواد',
                    'stage_order' => 3,
                    'status' => 'معلق',
                    'requires_approval' => false,
                ],
                [
                    'stage_name' => 'فرز',
                    'stage_order' => 4,
                    'status' => 'معلق',
                    'requires_approval' => true,
                ],
                [
                    'stage_name' => 'قص',
                    'stage_order' => 5,
                    'status' => 'معلق',
                    'requires_approval' => true,
                ],
                [
                    'stage_name' => 'تعبئة',
                    'stage_order' => 6,
                    'status' => 'معلق',
                    'requires_approval' => false,
                ],
                [
                    'stage_name' => 'فوترة',
                    'stage_order' => 7,
                    'status' => 'معلق',
                    'requires_approval' => true,
                ],
                [
                    'stage_name' => 'تسليم',
                    'stage_order' => 8,
                    'status' => 'معلق',
                    'requires_approval' => true,
                ],
            ];

            foreach ($stages as $stageData) {
                $stageData['order_id'] = $order->id;
                \App\Models\OrderStage::create($stageData);
            }

            // Move some orders to different stages for testing
            if ($order->id % 2 === 0) {
                $order->update(['current_stage' => 'مراجعة', 'status' => 'confirmed']);
                $stage = $order->stages()->where('stage_name', 'مراجعة')->first();
                if ($stage) {
                    $stage->update(['status' => 'قيد_التنفيذ']);
                }
            }
        }
    }
}