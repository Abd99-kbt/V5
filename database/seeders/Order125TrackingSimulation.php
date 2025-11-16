<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\{User, Warehouse, Product, Stock, Order, OrderItem, OrderStage};

class Order125TrackingSimulation extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds for Order 125 tracking simulation.
     */
    public function run(): void
    {
        $this->command->info('Starting Order 125 tracking simulation...');

        $users = User::all();
        $warehouses = Warehouse::all();
        $products = Product::all();

        // الحصول على المستخدمين المطلوبين
        $salesEmployee = $users->where('username', 'موظف_مبيعات')->first();
        $warehouseManager = $users->where('username', 'مسؤول_مستودع')->first();
        $sortingManager = $users->where('username', 'مسؤول_فرازة')->first();
        $cuttingManager = $users->where('username', 'مسؤول_قصاصة')->first();
        $deliveryManager = $users->where('username', 'مسؤول_تسليم')->first();
        $accountant = $users->where('username', 'محاسب')->first();

        // الحصول على المستودعات
        $mainWarehouse = $warehouses->where('type', 'main')->first();
        $sortingWarehouse = $warehouses->where('type', 'sorting')->first();
        $cuttingWarehouse = $warehouses->where('type', 'custody')->first();
        $wasteWarehouse = $warehouses->where('type', 'scrap')->first();
        $finishedWarehouse = $warehouses->where('type', 'custody')->first();
        $rollsWarehouse = $warehouses->where('type', 'main')->first();

        // الحصول على المنتجات
        $originalRoll = $products->where('sku', 'CB-PREM-180-200-125')->first();
        $sorted110Roll = $products->where('sku', 'CB-PREM-110-200-125-SORTED')->first();
        $sorted79Roll = $products->where('sku', 'CB-STD-79-200-125-SORTED')->first();
        $cutSheets = $products->where('sku', 'SHT-CUT-110-100-125')->first();
        $remnantRoll = $products->where('sku', 'RMT-110-90-125')->first();

        // إنشاء أو تحديث الطلب 125
        $order125 = Order::updateOrCreate(
            ['order_number' => '125'],
            [
                'warehouse_id' => $mainWarehouse?->id ?? 1,
                'customer_id' => 1, // سيتم تعيينه لاحقاً
                'created_by' => $salesEmployee?->id ?? 1,
                'assigned_to' => $salesEmployee?->id ?? 1,
                'material_type' => 'كرتون',
                'required_weight' => 1200.00,
                'estimated_price' => 1260.00,
                'delivery_method' => 'استلام_ذاتي',
                'notes' => 'طلب 125: كرتون 1200كغ، عرض 110سم، طول 100سم، غراماج 200',
                'is_urgent' => false,
                'order_date' => '2025-10-26',
                'current_stage' => 'قص',
                'status' => 'processing',
            ]
        );

        // إنشاء أو تحديث عنصر الطلب
        if ($originalRoll) {
            OrderItem::updateOrCreate(
                ['order_id' => $order125->id],
                [
                    'product_id' => $originalRoll->id,
                    'quantity' => 1200.00,
                    'unit_price' => 1.05,
                    'total_price' => 1260.00,
                    'notes' => 'رول كرتون مميز للطلب 125 - سيتم فرزه إلى 110سم',
                ]
            );
        }

        // إنشاء مراحل الطلب
        $stages = [
            [
                'stage_name' => 'إنشاء',
                'stage_order' => 1,
                'status' => 'مكتمل',
                'requires_approval' => false,
                'completed_at' => '2025-10-26 09:00:00',
                'notes' => 'تم إنشاء الطلب بواسطة موظف المبيعات',
            ],
            [
                'stage_name' => 'مراجعة',
                'stage_order' => 2,
                'status' => 'مكتمل',
                'requires_approval' => true,
                'completed_at' => '2025-10-26 10:30:00',
                'approved_by' => $salesEmployee?->id,
                'approved_at' => '2025-10-26 10:30:00',
                'approval_status' => 'معتمد',
                'notes' => 'تمت مراجعة واعتماد الطلب',
            ],
            [
                'stage_name' => 'حجز_المواد',
                'stage_order' => 3,
                'status' => 'مكتمل',
                'requires_approval' => false,
                'completed_at' => '2025-10-26 11:00:00',
                'notes' => 'تم حجز المواد من المستودع الرئيسي',
            ],
            [
                'stage_name' => 'فرز',
                'stage_order' => 4,
                'status' => 'مكتمل',
                'requires_approval' => true,
                'completed_at' => '2025-10-27 14:00:00',
                'approved_by' => $sortingManager?->id,
                'approved_at' => '2025-10-27 14:00:00',
                'approval_status' => 'معتمد',
                'notes' => 'تم فرز الرول 180سم إلى رول 110سم (1300كغ) ورول 79سم (600كغ) مع هدر 100كغ',
            ],
            [
                'stage_name' => 'قص',
                'stage_order' => 5,
                'status' => 'قيد_التنفيذ',
                'requires_approval' => true,
                'assigned_to' => $cuttingManager?->id,
                'notes' => 'جاري قص الرول 110سم إلى أطباق 110×100سم',
            ],
            [
                'stage_name' => 'تعبئة',
                'stage_order' => 6,
                'status' => 'معلق',
                'requires_approval' => false,
                'notes' => 'في الانتظار',
            ],
            [
                'stage_name' => 'فوترة',
                'stage_order' => 7,
                'status' => 'معلق',
                'requires_approval' => true,
                'assigned_to' => $accountant?->id,
                'notes' => 'في الانتظار',
            ],
            [
                'stage_name' => 'تسليم',
                'stage_order' => 8,
                'status' => 'معلق',
                'requires_approval' => true,
                'assigned_to' => $deliveryManager?->id,
                'notes' => 'في الانتظار',
            ],
        ];

        foreach ($stages as $stageData) {
            OrderStage::updateOrCreate(
                [
                    'order_id' => $order125->id,
                    'stage_name' => $stageData['stage_name'],
                ],
                $stageData
            );
        }

        // إعداد المخزون الأولي
        $this->setupInitialInventory($mainWarehouse, $originalRoll);
        
        // محاكاة عملية الفرز
        $this->simulateSortingProcess($mainWarehouse, $sortingWarehouse, $originalRoll, $sorted110Roll, $sorted79Roll, $wasteWarehouse);
        
        // محاكاة عملية القص
        $this->simulateCuttingProcess($sortingWarehouse, $cuttingWarehouse, $finishedWarehouse, $sorted110Roll, $cutSheets, $remnantRoll, $wasteWarehouse);

        $this->command->info('Order 125 tracking simulation completed successfully!');
    }

    private function setupInitialInventory($mainWarehouse, $originalRoll): void
    {
        // إعداد المخزون الأولي في المستودع الرئيسي
        if ($mainWarehouse && $originalRoll) {
            Stock::updateOrCreate([
                'product_id' => $originalRoll->id,
                'warehouse_id' => $mainWarehouse->id,
            ], [
                'quantity' => 2000.00, // الرول كاملاً
                'reserved_quantity' => 1200.00, // الجزء المحجوز للطلب 125
                'unit_cost' => $originalRoll->purchase_price,
                'is_active' => true,
            ]);
        }

        $this->command->info('Initial inventory setup completed');
    }

    private function simulateSortingProcess($mainWarehouse, $sortingWarehouse, $originalRoll, $sorted110Roll, $sorted79Roll, $wasteWarehouse): void
    {
        // بعد الموافقة على الفرز: انتقال من المستودع الرئيسي إلى مستودع الفرز
        // نقل 2000كغ من المستودع الرئيسي
        if ($mainWarehouse && $originalRoll) {
            $mainStock = Stock::where('product_id', $originalRoll->id)
                              ->where('warehouse_id', $mainWarehouse->id)
                              ->first();
            
            if ($mainStock) {
                $mainStock->update([
                    'quantity' => 0.00, // تم نقل كامل الرول
                    'reserved_quantity' => 0.00,
                ]);
            }
        }

        // إضافة المواد إلى مستودع الفرز
        // رول 110سم - 1300كغ
        if ($sortingWarehouse && $sorted110Roll) {
            Stock::updateOrCreate([
                'product_id' => $sorted110Roll->id,
                'warehouse_id' => $sortingWarehouse->id,
            ], [
                'quantity' => 1300.00,
                'reserved_quantity' => 1300.00, // محجوز للطلب 125
                'unit_cost' => $sorted110Roll->purchase_price,
                'is_active' => true,
            ]);
        }

        // رول 79سم - 600كغ
        if ($sortingWarehouse && $sorted79Roll) {
            Stock::updateOrCreate([
                'product_id' => $sorted79Roll->id,
                'warehouse_id' => $sortingWarehouse->id,
            ], [
                'quantity' => 600.00,
                'reserved_quantity' => 0.00, // غير محجوز
                'unit_cost' => $sorted79Roll->purchase_price,
                'is_active' => true,
            ]);
        }

        // هدر 100كغ في مستودع التهالك
        if ($wasteWarehouse && $sorted110Roll) {
            Stock::updateOrCreate([
                'product_id' => $sorted110Roll->id, // نفس نوع المادة
                'warehouse_id' => $wasteWarehouse->id,
            ], [
                'quantity' => 100.00,
                'reserved_quantity' => 0.00,
                'unit_cost' => 0.00, // هدر
                'is_active' => true,
            ]);
        }

        $this->command->info('Sorting process simulation completed');
    }

    private function simulateCuttingProcess($sortingWarehouse, $cuttingWarehouse, $finishedWarehouse, $sorted110Roll, $cutSheets, $remnantRoll, $wasteWarehouse): void
    {
        // انتقال من مستودع الفرز إلى مستودع القص
        if ($sortingWarehouse && $sorted110Roll) {
            $sortingStock = Stock::where('product_id', $sorted110Roll->id)
                               ->where('warehouse_id', $sortingWarehouse->id)
                               ->first();
            
            if ($sortingStock) {
                $sortingStock->update([
                    'quantity' => 0.00, // تم نقل كامل الرول
                    'reserved_quantity' => 0.00,
                ]);
            }
        }

        // إضافة المواد إلى مستودع القص
        // رول 110سم قبل القص - 1300كغ
        if ($cuttingWarehouse && $sorted110Roll) {
            Stock::updateOrCreate([
                'product_id' => $sorted110Roll->id,
                'warehouse_id' => $cuttingWarehouse->id,
            ], [
                'quantity' => 1300.00,
                'reserved_quantity' => 1200.00, // 1200كغ للطلب 125
                'unit_cost' => $sorted110Roll->purchase_price,
                'is_active' => true,
            ]);
        }

        // أطباق جاهزة للتسليم - 1200كغ
        if ($finishedWarehouse && $cutSheets) {
            Stock::updateOrCreate([
                'product_id' => $cutSheets->id,
                'warehouse_id' => $finishedWarehouse->id,
            ], [
                'quantity' => 1200.00,
                'reserved_quantity' => 1200.00, // جاهزة للتسليم
                'unit_cost' => $cutSheets->purchase_price,
                'is_active' => true,
            ]);
        }

        // رول متبقي من القص - 90كغ
        if ($cuttingWarehouse && $remnantRoll) {
            Stock::updateOrCreate([
                'product_id' => $remnantRoll->id,
                'warehouse_id' => $cuttingWarehouse->id,
            ], [
                'quantity' => 90.00,
                'reserved_quantity' => 0.00,
                'unit_cost' => $remnantRoll->purchase_price,
                'is_active' => true,
            ]);
        }

        // تلف 10كغ في مستودع التهالك
        if ($wasteWarehouse && $remnantRoll) {
            Stock::updateOrCreate([
                'product_id' => $remnantRoll->id, // نفس نوع المادة
                'warehouse_id' => $wasteWarehouse->id,
            ], [
                'quantity' => 10.00,
                'reserved_quantity' => 0.00,
                'unit_cost' => 0.00, // تلف
                'is_active' => true,
            ]);
        }

        $this->command->info('Cutting process simulation completed');
    }
}