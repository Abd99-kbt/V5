<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WorkStage;

class WorkStageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stages = [
            [
                'name_en' => 'Creation',
                'name_ar' => 'إنشاء',
                'description_en' => 'Order creation and initial setup',
                'description_ar' => 'إنشاء الطلب وإعداده الأولي',
                'order' => 1,
                'color' => 'gray',
                'icon' => 'heroicon-o-plus-circle',
                'can_skip' => false,
                'requires_role' => null,
                'estimated_duration' => 30,
                'stage_group' => 'preparation',
                'is_mandatory' => true,
                'is_active' => true,
            ],
            [
                'name_en' => 'Review',
                'name_ar' => 'مراجعة',
                'description_en' => 'Order review and approval',
                'description_ar' => 'مراجعة الطلب والموافقة عليه',
                'order' => 2,
                'color' => 'yellow',
                'icon' => 'heroicon-o-eye',
                'can_skip' => false,
                'requires_role' => 'مدير_مبيعات',
                'estimated_duration' => 60,
                'stage_group' => 'preparation',
                'is_mandatory' => true,
                'is_active' => true,
            ],
            [
                'name_en' => 'Material Reservation',
                'name_ar' => 'حجز_المواد',
                'description_en' => 'Reserve required materials from warehouse',
                'description_ar' => 'حجز المواد المطلوبة من المستودع',
                'order' => 3,
                'color' => 'blue',
                'icon' => 'heroicon-o-archive-box',
                'can_skip' => true,
                'requires_role' => 'مسؤول_مستودع',
                'estimated_duration' => 45,
                'stage_group' => 'processing',
                'is_mandatory' => false,
                'is_active' => true,
                'skip_conditions' => ['materials_available' => false],
            ],
            [
                'name_en' => 'Sorting',
                'name_ar' => 'فرز',
                'description_en' => 'Sort and prepare materials',
                'description_ar' => 'فرز وتحضير المواد',
                'order' => 4,
                'color' => 'purple',
                'icon' => 'heroicon-o-squares-2x2',
                'can_skip' => false,
                'requires_role' => 'مسؤول_فرازة',
                'estimated_duration' => 90,
                'stage_group' => 'processing',
                'is_mandatory' => true,
                'is_active' => true,
            ],
            [
                'name_en' => 'Cutting',
                'name_ar' => 'قص',
                'description_en' => 'Cut materials to required specifications',
                'description_ar' => 'قص المواد حسب المواصفات المطلوبة',
                'order' => 5,
                'color' => 'orange',
                'icon' => 'heroicon-o-scissors',
                'can_skip' => false,
                'requires_role' => 'مسؤول_قصاصة',
                'estimated_duration' => 120,
                'stage_group' => 'processing',
                'is_mandatory' => true,
                'is_active' => true,
            ],
            [
                'name_en' => 'Packaging',
                'name_ar' => 'تعبئة',
                'description_en' => 'Package finished products',
                'description_ar' => 'تعبئة المنتجات النهائية',
                'order' => 6,
                'color' => 'indigo',
                'icon' => 'heroicon-o-gift',
                'can_skip' => true,
                'requires_role' => null,
                'estimated_duration' => 30,
                'stage_group' => 'processing',
                'is_mandatory' => false,
                'is_active' => true,
            ],
            [
                'name_en' => 'Invoicing',
                'name_ar' => 'فوترة',
                'description_en' => 'Create and process invoices',
                'description_ar' => 'إنشاء ومعالجة الفواتير',
                'order' => 7,
                'color' => 'green',
                'icon' => 'heroicon-o-document-text',
                'can_skip' => false,
                'requires_role' => 'محاسب',
                'estimated_duration' => 60,
                'stage_group' => 'delivery',
                'is_mandatory' => true,
                'is_active' => true,
            ],
            [
                'name_en' => 'Delivery',
                'name_ar' => 'تسليم',
                'description_en' => 'Deliver order to customer',
                'description_ar' => 'تسليم الطلب للعميل',
                'order' => 8,
                'color' => 'emerald',
                'icon' => 'heroicon-o-truck',
                'can_skip' => false,
                'requires_role' => 'مسؤول_تسليم',
                'estimated_duration' => 45,
                'stage_group' => 'delivery',
                'is_mandatory' => true,
                'is_active' => true,
            ],
        ];

        foreach ($stages as $stage) {
            WorkStage::updateOrCreate(
                ['name_en' => $stage['name_en']],
                $stage
            );
        }
    }
}
