<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name_en' => 'Cardboard & Paper',
                'name_ar' => 'كرتون وورق',
                'description_en' => 'Cardboard, paper, and packaging materials',
                'description_ar' => 'الكرتون والورق ومواد التعبئة',
                'is_active' => true,
            ],
            [
                'name_en' => 'Electronics',
                'name_ar' => 'إلكترونيات',
                'description_en' => 'Electronic devices and accessories',
                'description_ar' => 'الأجهزة الإلكترونية والإكسسوارات',
                'is_active' => true,
            ],
            [
                'name_en' => 'Clothing',
                'name_ar' => 'ملابس',
                'description_en' => 'Clothing and fashion items',
                'description_ar' => 'الملابس ومواد الموضة',
                'is_active' => true,
            ],
            [
                'name_en' => 'Food & Beverages',
                'name_ar' => 'المواد الغذائية والمشروبات',
                'description_en' => 'Food products and beverages',
                'description_ar' => 'المنتجات الغذائية والمشروبات',
                'is_active' => true,
            ],
            [
                'name_en' => 'Home & Garden',
                'name_ar' => 'المنزل والحديقة',
                'description_en' => 'Home appliances and garden supplies',
                'description_ar' => 'أدوات المنزل ومستلزمات الحديقة',
                'is_active' => true,
            ],
            [
                'name_en' => 'Books & Media',
                'name_ar' => 'كتب ووسائط',
                'description_en' => 'Books, magazines, and digital media',
                'description_ar' => 'الكتب والمجلات والوسائط الرقمية',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            \App\Models\Category::create($category);
        }
    }
}
