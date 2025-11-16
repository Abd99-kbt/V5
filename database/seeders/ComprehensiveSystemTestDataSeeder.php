<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\{Customer, Product, Warehouse, Stock, Order, OrderItem, OrderStage, User};

class ComprehensiveSystemTestDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds for comprehensive system testing.
     */
    public function run(): void
    {
        $this->command->info('Creating comprehensive system test data for tracking and weight control...');

        // Get existing data
        $warehouses = Warehouse::all();
        $users = User::all();
        $customers = Customer::all();
        $products = Product::all();

        // 1. Create additional warehouses for different stages
        $this->createAdditionalWarehouses();
        
        // 2. Create comprehensive test materials (products) with real inventory
        $this->createTestMaterials();
        
        // 3. Create test customers
        $this->createTestCustomers();
        
        // 4. Set up initial inventory across all warehouses
        $this->setupTestInventory();
        
        // 5. Create comprehensive test orders with tracking
        $this->createTestOrders();
        
        $this->command->info('Comprehensive system test data created successfully!');
    }

    private function createAdditionalWarehouses(): void
    {
        $warehouses = [
            [
                'name_ar' => 'مستودع قصاصات',
                'name_en' => 'Cutting Warehouse',
                'code' => 'WH-CUT-001',
                'type' => 'custody', // استخدام custody لأنواع القص
                'address_ar' => 'المنطقة الصناعية بدمشق - مبنى 3',
                'address_en' => 'Damascus Industrial Zone - Building 3',
                'is_active' => true,
                'is_main' => false,
                'accepts_transfers' => true,
                'requires_approval' => false,
                'total_capacity' => 5000.00,
                'used_capacity' => 0.00,
                'reserved_capacity' => 0.00,
            ],
            [
                'name_ar' => 'مستودع تهالك',
                'name_en' => 'Waste Warehouse',
                'code' => 'WH-WST-001',
                'type' => 'scrap',
                'address_ar' => 'المنطقة الصناعية بدمشق - إدارة المخلفات',
                'address_en' => 'Damascus Industrial Zone - Waste Management Area',
                'is_active' => true,
                'is_main' => false,
                'accepts_transfers' => true,
                'requires_approval' => false,
                'total_capacity' => 2000.00,
                'used_capacity' => 0.00,
                'reserved_capacity' => 0.00,
            ],
            [
                'name_ar' => 'مستودع بضائع جاهزة',
                'name_en' => 'Finished Goods Warehouse',
                'code' => 'WH-FIN-001',
                'type' => 'custody', // استخدام custody للبضائع الجاهزة
                'address_ar' => 'المنطقة الصناعية بدمشق - البضائع الجاهزة',
                'address_en' => 'Damascus Industrial Zone - Finished Products',
                'is_active' => true,
                'is_main' => false,
                'accepts_transfers' => true,
                'requires_approval' => true,
                'total_capacity' => 3000.00,
                'used_capacity' => 0.00,
                'reserved_capacity' => 0.00,
            ],
            [
                'name_ar' => 'مستودع رولات إضافية',
                'name_en' => 'Additional Rolls Warehouse',
                'code' => 'WH-ROL-001',
                'type' => 'main', // استخدام main للرولات الإضافية
                'address_ar' => 'المنطقة الصناعية بدمشق - تخزين الرولات',
                'address_en' => 'Damascus Industrial Zone - Roll Storage',
                'is_active' => true,
                'is_main' => false,
                'accepts_transfers' => true,
                'requires_approval' => false,
                'total_capacity' => 4000.00,
                'used_capacity' => 0.00,
                'reserved_capacity' => 0.00,
            ],
        ];

        foreach ($warehouses as $warehouse) {
            \App\Models\Warehouse::updateOrCreate(
                ['code' => $warehouse['code']],
                $warehouse
            );
        }

        $this->command->info('Created 4 additional warehouses');
    }

    private function createTestMaterials(): void
    {
        $testProducts = [
            // طلب رقم 125 - رول أساسي
            [
                'name_en' => 'Premium Cardboard Roll - 180cm Width',
                'name_ar' => 'رول كرتون مميز - عرض 180 سم',
                'sku' => 'CB-PREM-180-200-125',
                'barcode' => '1901992255180',
                'type' => 'roll',
                'grammage' => 200,
                'quality' => 'premium',
                'roll_number' => 'R125-001',
                'source' => 'Germany',
                'length' => null,
                'width' => 180.00,
                'thickness' => 0.20,
                'purchase_price' => 950.00,
                'selling_price' => 1050.00,
                'unit' => 'kg',
                'weight' => 2000.00,
                'available_weight_kg' => 2000.00,
            ],
            // رول 110 سم نتيجة الفرز
            [
                'name_en' => 'Premium Cardboard Roll - 110cm Width',
                'name_ar' => 'رول كرتون مميز - عرض 110 سم',
                'sku' => 'CB-PREM-110-200-125-SORTED',
                'barcode' => '1901992255110',
                'type' => 'roll',
                'grammage' => 200,
                'quality' => 'premium',
                'roll_number' => 'R125-110-SORTED',
                'source' => 'Sorted from 180cm',
                'length' => null,
                'width' => 110.00,
                'thickness' => 0.20,
                'purchase_price' => 1050.00,
                'selling_price' => 1150.00,
                'unit' => 'kg',
                'weight' => 1300.00,
                'available_weight_kg' => 1300.00,
            ],
            // رول 79 سم نتيجة الفرز
            [
                'name_en' => 'Standard Cardboard Roll - 79cm Width',
                'name_ar' => 'رول كرتون قياسي - عرض 79 سم',
                'sku' => 'CB-STD-79-200-125-SORTED',
                'barcode' => '1901992255079',
                'type' => 'roll',
                'grammage' => 200,
                'quality' => 'standard',
                'roll_number' => 'R125-79-SORTED',
                'source' => 'Sorted from 180cm',
                'length' => null,
                'width' => 79.00,
                'thickness' => 0.20,
                'purchase_price' => 850.00,
                'selling_price' => 950.00,
                'unit' => 'kg',
                'weight' => 600.00,
                'available_weight_kg' => 600.00,
            ],
            // أطباق جاهزة للتسليم - طلب 125
            [
                'name_en' => 'Cut Sheets - 110x100cm - Order 125',
                'name_ar' => 'أطباق مقصوصة - 110×100 سم - طلب 125',
                'sku' => 'SHT-CUT-110-100-125',
                'barcode' => '1901992255100',
                'type' => 'digma', // استخدام digma بدلاً من sheets
                'grammage' => 200,
                'quality' => 'premium',
                'roll_number' => null,
                'source' => 'Cut from 110cm roll',
                'length' => 100.00,
                'width' => 110.00,
                'thickness' => 0.20,
                'purchase_price' => 1150.00,
                'selling_price' => 1250.00,
                'unit' => 'kg',
                'weight' => 1200.00,
                'available_weight_kg' => 1200.00,
            ],
            // رول صغير من القص
            [
                'name_en' => 'Remnant Roll - 110x90cm',
                'name_ar' => 'رول متبقي - 110×90 سم',
                'sku' => 'RMT-110-90-125',
                'barcode' => '1901992255090',
                'type' => 'roll',
                'grammage' => 200,
                'quality' => 'standard',
                'roll_number' => 'R125-REMNANT',
                'source' => 'Cutting Remnant',
                'length' => null,
                'width' => 110.00,
                'thickness' => 0.20,
                'purchase_price' => 800.00,
                'selling_price' => 900.00,
                'unit' => 'kg',
                'weight' => 90.00,
                'available_weight_kg' => 90.00,
            ],
            // مواد إضافية للاختبار
            [
                'name_en' => 'Standard Cardboard Roll - 120cm',
                'name_ar' => 'رول كرتون قياسي - عرض 120 سم',
                'sku' => 'CB-STD-120-180-TEST',
                'barcode' => '1901992257120',
                'type' => 'roll',
                'grammage' => 180,
                'quality' => 'standard',
                'roll_number' => 'STD120-180-001',
                'source' => 'Turkey',
                'length' => null,
                'width' => 120.00,
                'thickness' => 0.18,
                'purchase_price' => 750.00,
                'selling_price' => 850.00,
                'unit' => 'kg',
                'weight' => 3000.00,
                'available_weight_kg' => 3000.00,
            ],
            [
                'name_en' => 'Heavy Duty Cardboard - 150cm',
                'name_ar' => 'كرتون ثقيل - عرض 150 سم',
                'sku' => 'CB-HD-150-250-TEST',
                'barcode' => '1901992257150',
                'type' => 'roll',
                'grammage' => 250,
                'quality' => 'premium',
                'roll_number' => 'HD150-250-001',
                'source' => 'Italy',
                'length' => null,
                'width' => 150.00,
                'thickness' => 0.25,
                'purchase_price' => 1200.00,
                'selling_price' => 1300.00,
                'unit' => 'kg',
                'weight' => 2500.00,
                'available_weight_kg' => 2500.00,
            ],
        ];

        foreach ($testProducts as $productData) {
            Product::updateOrCreate(
                ['sku' => $productData['sku']],
                $productData + [
                    'description_en' => 'Test material for order tracking system',
                    'description_ar' => 'مادة تجريبية لنظام تتبع الطلبات',
                    'min_stock_level' => 10,
                    'max_stock_level' => 1000,
                    'reserved_weight' => 0.00,
                    'is_active' => true,
                    'track_inventory' => true,
                    'category_id' => 1,
                    'supplier_id' => 1,
                    'purchase_invoice_number' => 'INV-TEST-' . date('Y'),
                ]
            );
        }

        $this->command->info('Created 7 test materials');
    }

    private function createTestCustomers(): void
    {
        $additionalCustomers = [
            [
                'name_en' => 'Damascus Packaging Company',
                'name_ar' => 'شركة تعبئة دمشق',
                'province_en' => 'Damascus',
                'province_ar' => 'دمشق',
                'mobile_number' => '963933123456',
                'follow_up_person_en' => 'Omar Al-Masri',
                'follow_up_person_ar' => 'عمر المصري',
                'address_en' => 'Damascus Industrial Zone, Building 15',
                'address_ar' => 'المنطقة الصناعية بدمشق، مبنى 15',
                'email' => 'info@damascuspackaging.com',
                'customer_type' => 'company',
                'customer_location' => 'Damascus Industrial Zone',
                'account_representative' => 'Sales Team A',
            ],
            [
                'name_en' => 'Aleppo Trading Est.',
                'name_ar' => 'مؤسسة تجارة حلب',
                'province_en' => 'Aleppo',
                'province_ar' => 'حلب',
                'mobile_number' => '963212345678',
                'follow_up_person_en' => 'Fatima Al-Hussein',
                'follow_up_person_ar' => 'فاطمة الحسين',
                'address_en' => 'New Aleppo Industrial City',
                'address_ar' => 'مدينة حلب الصناعية الجديدة',
                'email' => 'contact@aleppotrading.com',
                'customer_type' => 'company',
                'customer_location' => 'Aleppo Industrial Zone',
                'account_representative' => 'Sales Team B',
            ],
            [
                'name_en' => 'Homs Paper Factory',
                'name_ar' => 'مصنع ورق حمص',
                'province_en' => 'Homs',
                'province_ar' => 'حمص',
                'mobile_number' => '963311234567',
                'follow_up_person_en' => 'Ahmad Al-Khatib',
                'follow_up_person_ar' => 'أحمد الخطيب',
                'address_en' => 'Homs Industrial City, Zone 3',
                'address_ar' => 'مدينة حمص الصناعية، المنطقة 3',
                'email' => 'sales@homspaperfactory.com',
                'customer_type' => 'company',
                'customer_location' => 'Homs Industrial Zone',
                'account_representative' => 'Sales Team A',
            ],
        ];

        foreach ($additionalCustomers as $customer) {
            Customer::create($customer + [
                'is_active' => true,
            ]);
        }

        $this->command->info('Created 3 additional test customers');
    }

    private function setupTestInventory(): void
    {
        $warehouses = Warehouse::all();
        $products = Product::all();
        $mainWarehouse = $warehouses->where('type', 'main')->first();
        $sortingWarehouse = $warehouses->where('type', 'sorting')->first();
        $cuttingWarehouse = $warehouses->where('type', 'cutting')->first();
        $wasteWarehouse = $warehouses->where('type', 'waste')->first();
        $finishedWarehouse = $warehouses->where('type', 'finished_goods')->first();
        $rollsWarehouse = $warehouses->where('type', 'rolls_storage')->first();

        // Main warehouse inventory
        $mainWarehouseProducts = [
            'CB-PREM-180-200-125' => 2000.00,  // Original 180cm roll
            'CB-STD-120-180-TEST' => 2500.00,   // Standard roll
            'CB-HD-150-250-TEST' => 2000.00,    // Heavy duty roll
        ];

        foreach ($mainWarehouseProducts as $sku => $quantity) {
            $product = $products->where('sku', $sku)->first();
            if ($product && $mainWarehouse) {
                Stock::updateOrCreate([
                    'product_id' => $product->id,
                    'warehouse_id' => $mainWarehouse->id,
                ], [
                    'quantity' => $quantity,
                    'reserved_quantity' => 0.00,
                    'unit_cost' => $product->purchase_price,
                    'is_active' => true,
                ]);
            }
        }

        // Sorting warehouse (for future sorting operations)
        $sortingWarehouseProducts = [
            'CB-STD-120-180-TEST' => 500.00,
        ];

        foreach ($sortingWarehouseProducts as $sku => $quantity) {
            $product = $products->where('sku', $sku)->first();
            if ($product && $sortingWarehouse) {
                Stock::updateOrCreate([
                    'product_id' => $product->id,
                    'warehouse_id' => $sortingWarehouse->id,
                ], [
                    'quantity' => $quantity,
                    'reserved_quantity' => 0.00,
                    'unit_cost' => $product->purchase_price,
                    'is_active' => true,
                ]);
            }
        }

        // Set up warehouse stock for completed materials
        $completedMaterials = [
            'CB-PREM-110-200-125-SORTED' => 1300.00,
            'CB-STD-79-200-125-SORTED' => 600.00,
        ];

        foreach ($completedMaterials as $sku => $quantity) {
            $product = $products->where('sku', $sku)->first();
            if ($product) {
                // Add to sorting warehouse (result of sorting)
                if ($sortingWarehouse) {
                    Stock::updateOrCreate([
                        'product_id' => $product->id,
                        'warehouse_id' => $sortingWarehouse->id,
                    ], [
                        'quantity' => $quantity,
                        'reserved_quantity' => 0.00,
                        'unit_cost' => $product->purchase_price,
                        'is_active' => true,
                    ]);
                }
            }
        }

        $this->command->info('Set up test inventory across warehouses');
    }

    private function createTestOrders(): void
    {
        $warehouses = Warehouse::all();
        $users = User::all();
        $customers = Customer::all();
        $products = Product::all();

        $mainWarehouse = $warehouses->where('type', 'main')->first();
        $sortingWarehouse = $warehouses->where('type', 'sorting')->first();
        $cuttingWarehouse = $warehouses->where('type', 'cutting')->first();

        // الطلب الأساسي 125 - مثال توضيحي شامل
        $order125 = Order::create([
            'order_number' => '125',
            'warehouse_id' => $mainWarehouse->id,
            'customer_id' => $customers->first()->id,
            'created_by' => $users->where('username', 'موظف_مبيعات')->first()->id,
            'assigned_to' => $users->where('username', 'موظف_مبيعات')->first()->id,
            'material_type' => 'كرتون',
            'required_weight' => 1200.00,
            'estimated_price' => 1260.00,
            'delivery_method' => 'استلام_ذاتي',
            'notes' => 'طلب 125: كرتون 1200كغ، عرض 110سم، طول 100سم، غراماج 200',
            'is_urgent' => false,
            'order_date' => '2025-10-26',
            'current_stage' => 'قص',
            'status' => 'processing',
        ]);

        $mainProduct = $products->where('sku', 'CB-PREM-180-200-125')->first();
        OrderItem::create([
            'order_id' => $order125->id,
            'product_id' => $mainProduct->id,
            'quantity' => 1200.00,
            'unit_price' => 1.05,
            'total_price' => 1260.00,
            'notes' => 'رول كرتون مميز للطلب 125 - سيتم فرزه إلى 110سم',
        ]);

        // إنشاء مراحل الطلب
        $stages = [
            ['stage_name' => 'إنشاء', 'stage_order' => 1, 'status' => 'مكتمل', 'requires_approval' => false, 'completed_at' => '2025-10-26 09:00:00'],
            ['stage_name' => 'مراجعة', 'stage_order' => 2, 'status' => 'مكتمل', 'requires_approval' => true, 'completed_at' => '2025-10-26 10:30:00'],
            ['stage_name' => 'حجز_المواد', 'stage_order' => 3, 'status' => 'مكتمل', 'requires_approval' => false, 'completed_at' => '2025-10-26 11:00:00'],
            ['stage_name' => 'فرز', 'stage_order' => 4, 'status' => 'مكتمل', 'requires_approval' => true, 'completed_at' => '2025-10-27 14:00:00'],
            ['stage_name' => 'قص', 'stage_order' => 5, 'status' => 'قيد_التنفيذ', 'requires_approval' => true, 'assigned_to' => $users->where('username', 'مسؤول_قصاصة')->first()->id],
            ['stage_name' => 'تعبئة', 'stage_order' => 6, 'status' => 'معلق', 'requires_approval' => false],
            ['stage_name' => 'فوترة', 'stage_order' => 7, 'status' => 'معلق', 'requires_approval' => true],
            ['stage_name' => 'تسليم', 'stage_order' => 8, 'status' => 'معلق', 'requires_approval' => true],
        ];

        foreach ($stages as $stageData) {
            $stageData['order_id'] = $order125->id;
            OrderStage::create($stageData);
        }

        // طلبات إضافية للاختبار
        $supportingOrders = [
            [
                'order_number' => '126',
                'customer_id' => $customers->skip(1)->first()->id ?? $customers->first()->id,
                'created_by' => $users->where('username', 'موظف_مبيعات')->first()->id,
                'required_weight' => 1500.00,
                'current_stage' => 'فرز',
                'status' => 'processing',
                'material_type' => 'كرتون',
                'order_date' => '2025-10-27',
            ],
            [
                'order_number' => '127',
                'customer_id' => $customers->skip(2)->first()->id ?? $customers->first()->id,
                'created_by' => $users->where('username', 'موظف_مبيعات')->first()->id,
                'required_weight' => 800.00,
                'current_stage' => 'قص',
                'status' => 'processing',
                'material_type' => 'ورق',
                'order_date' => '2025-10-28',
            ],
            [
                'order_number' => '128',
                'customer_id' => $customers->first()->id,
                'created_by' => $users->where('username', 'موظف_مبيعات')->first()->id,
                'required_weight' => 2000.00,
                'current_stage' => 'تعبئة',
                'status' => 'processing',
                'material_type' => 'كرتون',
                'order_date' => '2025-10-29',
            ],
        ];

        foreach ($supportingOrders as $orderData) {
            $order = Order::create($orderData + [
                'warehouse_id' => $mainWarehouse->id,
                'assigned_to' => $users->where('username', 'موظف_مبيعات')->first()->id,
                'estimated_price' => $orderData['required_weight'] * 1.1,
                'delivery_method' => 'توصيل',
                'notes' => "طلب تجريبي رقم {$orderData['order_number']}",
            ]);

            $product = $products->skip(rand(0, 2))->first() ?? $products->first();
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $orderData['required_weight'],
                'unit_price' => $product->selling_price / 1000,
                'total_price' => $orderData['required_weight'] * ($product->selling_price / 1000),
                'notes' => "مادة الطلب رقم {$orderData['order_number']}",
            ]);

            // إنشاء مراحل أساسية للطلبات
            $basicStages = [
                ['stage_name' => 'إنشاء', 'stage_order' => 1, 'status' => 'مكتمل', 'requires_approval' => false, 'completed_at' => now()->toDateString()],
                ['stage_name' => 'مراجعة', 'stage_order' => 2, 'status' => 'مكتمل', 'requires_approval' => true, 'completed_at' => now()->toDateString()],
                ['stage_name' => 'حجز_المواد', 'stage_order' => 3, 'status' => 'مكتمل', 'requires_approval' => false, 'completed_at' => now()->toDateString()],
                ['stage_name' => 'فرز', 'stage_order' => 4, 'status' => in_array($orderData['current_stage'], ['فرز', 'قص', 'تعبئة']) ? 'مكتمل' : 'معلق', 'requires_approval' => true, 'completed_at' => in_array($orderData['current_stage'], ['فرز', 'قص', 'تعبئة']) ? now()->toDateString() : null],
                ['stage_name' => 'قص', 'stage_order' => 5, 'status' => in_array($orderData['current_stage'], ['قص', 'تعبئة']) ? 'مكتمل' : 'معلق', 'requires_approval' => true, 'completed_at' => in_array($orderData['current_stage'], ['قص', 'تعبئة']) ? now()->toDateString() : null],
                ['stage_name' => 'تعبئة', 'stage_order' => 6, 'status' => $orderData['current_stage'] == 'تعبئة' ? 'قيد_التنفيذ' : 'معلق', 'requires_approval' => false],
                ['stage_name' => 'فوترة', 'stage_order' => 7, 'status' => 'معلق', 'requires_approval' => true],
                ['stage_name' => 'تسليم', 'stage_order' => 8, 'status' => 'معلق', 'requires_approval' => true],
            ];

            foreach ($basicStages as $stageData) {
                $stageData['order_id'] = $order->id;
                OrderStage::create($stageData);
            }
        }

        $this->command->info('Created comprehensive test orders with tracking stages');
    }
}