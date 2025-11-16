<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = [
            [
                'name_en' => 'Main Warehouse 1',
                'name_ar' => 'المستودع الرئيسي 1',
                'code' => 'WH001',
                'address_en' => 'Industrial Area, Plot 123, Dubai, UAE',
                'address_ar' => 'المنطقة الصناعية، قطعة 123، دبي، الإمارات العربية المتحدة',
                'phone' => '+971-4-1234567',
                'manager_name' => 'Ahmed Hassan',
                'type' => 'main',
                'total_capacity' => 10000.00,
                'used_capacity' => 0.00,
                'reserved_capacity' => 0.00,
                'is_active' => true,
                'is_main' => true,
                'accepts_transfers' => true,
                'requires_approval' => false,
            ],
            [
                'name_en' => 'Main Warehouse 2',
                'name_ar' => 'المستودع الرئيسي 2',
                'code' => 'WH002',
                'address_en' => 'Jebel Ali Free Zone, Warehouse 456, Dubai, UAE',
                'address_ar' => 'منطقة جبل علي الحرة، مستودع 456، دبي، الإمارات العربية المتحدة',
                'phone' => '+971-4-7654321',
                'manager_name' => 'Fatima Al-Zahra',
                'type' => 'main',
                'total_capacity' => 8000.00,
                'used_capacity' => 0.00,
                'reserved_capacity' => 0.00,
                'is_active' => true,
                'is_main' => false,
                'accepts_transfers' => true,
                'requires_approval' => false,
            ],
            [
                'name_en' => 'Scrap Warehouse 1',
                'name_ar' => 'مستودع خردة 1',
                'code' => 'WH003',
                'address_en' => 'Scrap Processing Facility, Sharjah, UAE',
                'address_ar' => 'منشأة معالجة الخردة، الشارقة، الإمارات العربية المتحدة',
                'phone' => '+971-6-1122334',
                'manager_name' => 'Omar Abdullah',
                'type' => 'scrap',
                'total_capacity' => 3000.00,
                'used_capacity' => 0.00,
                'reserved_capacity' => 0.00,
                'is_active' => true,
                'is_main' => false,
                'accepts_transfers' => true,
                'requires_approval' => true,
            ],
            [
                'name_en' => 'Sorting Warehouse 1',
                'name_ar' => 'مستودع فرز 1',
                'code' => 'WH004',
                'address_en' => 'Sorting Facility, Dubai Industrial Area',
                'address_ar' => 'منشأة الفرز، المنطقة الصناعية بدبي',
                'phone' => '+971-4-5566778',
                'manager_name' => 'Sara Al-Mansouri',
                'type' => 'sorting',
                'total_capacity' => 2000.00,
                'used_capacity' => 0.00,
                'reserved_capacity' => 0.00,
                'is_active' => true,
                'is_main' => false,
                'accepts_transfers' => true,
                'requires_approval' => true,
            ],
            [
                'name_en' => 'Custody Warehouse',
                'name_ar' => 'مستودع الحضانة',
                'code' => 'WH005',
                'address_en' => 'Custody Facility, Dubai, UAE',
                'address_ar' => 'منشأة الحضانة، دبي، الإمارات العربية المتحدة',
                'phone' => '+971-4-9988776',
                'manager_name' => 'Hassan Al-Rashid',
                'type' => 'custody',
                'total_capacity' => 1500.00,
                'used_capacity' => 0.00,
                'reserved_capacity' => 0.00,
                'is_active' => true,
                'is_main' => false,
                'accepts_transfers' => true,
                'requires_approval' => true,
            ],
        ];

        foreach ($warehouses as $warehouse) {
            \App\Models\Warehouse::create($warehouse);
        }
    }
}
