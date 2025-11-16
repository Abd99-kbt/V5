<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name_en' => 'TechCorp Electronics',
                'name_ar' => 'تيك كورب إلكترونيات',
                'contact_person_en' => 'John Smith',
                'contact_person_ar' => 'جون سميث',
                'email' => 'contact@techcorp.com',
                'phone' => '+1-555-0101',
                'address_en' => '123 Technology Street, Silicon Valley, CA 94043',
                'address_ar' => '123 شارع التكنولوجيا، وادي السيليكون، كاليفورنيا 94043',
                'tax_number' => 'TX123456789',
                'commercial_register' => 'CR987654321',
                'credit_limit' => 50000.00,
                'payment_terms' => 30,
                'is_active' => true,
            ],
            [
                'name_en' => 'Global Fashion Ltd',
                'name_ar' => 'جلوبال فاشن المحدودة',
                'contact_person_en' => 'Sarah Johnson',
                'contact_person_ar' => 'سارة جونسون',
                'email' => 'orders@globalfashion.com',
                'phone' => '+1-555-0102',
                'address_en' => '456 Fashion Avenue, New York, NY 10001',
                'address_ar' => '456 شارع الموضة، نيويورك، نيويورك 10001',
                'tax_number' => 'TX987654321',
                'commercial_register' => 'CR123456789',
                'credit_limit' => 25000.00,
                'payment_terms' => 15,
                'is_active' => true,
            ],
            [
                'name_en' => 'Fresh Foods Co',
                'name_ar' => 'شركة الأطعمة الطازجة',
                'contact_person_en' => 'Michael Brown',
                'contact_person_ar' => 'مايكل براون',
                'email' => 'supply@freshfoods.com',
                'phone' => '+1-555-0103',
                'address_en' => '789 Agriculture Road, California, CA 90210',
                'address_ar' => '789 طريق الزراعة، كاليفورنيا، كاليفورنيا 90210',
                'tax_number' => 'TX456789123',
                'commercial_register' => 'CR456789123',
                'credit_limit' => 15000.00,
                'payment_terms' => 7,
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $supplier) {
            \App\Models\Supplier::create($supplier);
        }
    }
}
