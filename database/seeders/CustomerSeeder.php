<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            [
                'name_en' => 'Printing Aspir',
                'name_ar' => 'مطبعة أسبر',
                'province_en' => 'Aleppo',
                'province_ar' => 'حلب',
                'mobile_number' => '933489002',
                'follow_up_person_en' => 'Omar Tarfi',
                'follow_up_person_ar' => 'عمر الطرفي',
                'address_en' => 'Aleppo Industrial Area',
                'address_ar' => 'المنطقة الصناعية بحلب',
                'email' => 'info@printingaspir.com',
                'customer_type' => 'company',
                'is_active' => true,
                // New business requirement fields
                'customer_location' => 'Aleppo Industrial Zone',
                'account_representative' => 'Sales Team A',
            ],
            [
                'name_en' => 'Guidance Press',
                'name_ar' => 'مطبعة الهداية',
                'province_en' => 'Damascus',
                'province_ar' => 'دمشق',
                'mobile_number' => '959960900',
                'follow_up_person_en' => 'Muntasar Mazid',
                'follow_up_person_ar' => 'منتصر مزيد',
                'address_en' => 'Damascus Industrial Zone',
                'address_ar' => 'المنطقة الصناعية بدمشق',
                'email' => 'contact@guidancepress.com',
                'customer_type' => 'company',
                'is_active' => true,
                // New business requirement fields
                'customer_location' => 'Damascus Industrial District',
                'account_representative' => 'Sales Team B',
            ],
            [
                'name_en' => 'Ayman Sidawi',
                'name_ar' => 'أيمن السيداوي',
                'province_en' => 'Hama',
                'province_ar' => 'حماة',
                'mobile_number' => '999253514',
                'follow_up_person_en' => 'Badr Nashed',
                'follow_up_person_ar' => 'بدر الناشد',
                'address_en' => 'Hama City Center',
                'address_ar' => 'وسط مدينة حماة',
                'email' => 'ayman.sidawi@email.com',
                'customer_type' => 'individual',
                'is_active' => true,
                // New business requirement fields
                'customer_location' => 'Hama Central Area',
                'account_representative' => 'Individual Sales Rep',
            ],
        ];

        foreach ($customers as $customer) {
            \App\Models\Customer::create($customer);
        }
    }
}
