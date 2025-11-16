<?php

return [
    // Labels
    'product' => 'المنتج',
    'warehouse' => 'المستودع',
    'type' => 'النوع',
    'severity' => 'الشدة',
    'current_quantity' => 'الكمية الحالية',
    'threshold_quantity' => 'كمية العتبة',
    'message' => 'الرسالة',
    'is_read' => 'مقروء',
    'is_resolved' => 'محلول',
    'resolved_at' => 'تاريخ الحل',
    'created_at' => 'تاريخ الإنشاء',
    'updated_at' => 'تاريخ التحديث',

    // Options
    'type_options' => [
        'low_stock' => 'مخزون منخفض',
        'out_of_stock' => 'نفد المخزون',
        'expiring_soon' => 'ينتهي قريباً',
        'expired' => 'منتهي الصلاحية',
    ],
    'severity_options' => [
        'low' => 'منخفض',
        'medium' => 'متوسط',
        'high' => 'عالي',
        'critical' => 'حرج',
    ],
    'is_read_options' => [
        true => 'مقروء',
        false => 'غير مقروء',
    ],
    'is_resolved_options' => [
        true => 'محلول',
        false => 'غير محلول',
    ],

    // Actions
    'create' => 'إنشاء تنبيه مخزون',
    'edit' => 'تعديل التنبيه',
    'delete' => 'حذف التنبيه',
    'list' => 'قائمة التنبيهات',

    // Messages
    'created_successfully' => 'تم إنشاء تنبيه المخزون بنجاح',
    'updated_successfully' => 'تم تحديث تنبيه المخزون بنجاح',
    'deleted_successfully' => 'تم حذف تنبيه المخزون بنجاح',
    'confirm_delete' => 'هل أنت متأكد من حذف هذا التنبيه؟',
];