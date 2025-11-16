<?php

return [
    // General
    'dashboard' => 'لوحة التحكم',
    'warehouse_management' => 'إدارة المستودعات',
    'inventory' => 'المخزون',
    'products' => 'المنتجات',
    'categories' => 'الفئات',
    'suppliers' => 'الموردين',
    'warehouses' => 'المستودعات',
    'orders' => 'الطلبات',
    'stock' => 'المخزون',
    'reports' => 'التقارير',
    'settings' => 'الإعدادات',
    'customers' => 'العملاء',
    'transfers' => 'التحويلات',
    'invoices' => 'الفواتير',
    'analytics' => 'التحليلات',
    'work_stages' => 'مراحل العمل',
    'approvals' => 'الموافقات',
    'waste' => 'النفايات',

    // Actions
    'add' => 'إضافة',
    'edit' => 'تعديل',
    'delete' => 'حذف',
    'save' => 'حفظ',
    'cancel' => 'إلغاء',
    'search' => 'بحث',
    'filter' => 'تصفية',
    'export' => 'تصدير',
    'import' => 'استيراد',
    'print' => 'طباعة',
    'view' => 'عرض',
    'approve' => 'موافقة',
    'reject' => 'رفض',
    'transfer' => 'تحويل',
    'receive' => 'استلام',
    'deliver' => 'تسليم',
    'sort' => 'فرز',
    'cut' => 'قص',
    'drag' => 'سحب',
    'drop' => 'إفلات',

    // Status
    'active' => 'نشط',
    'inactive' => 'غير نشط',
    'pending' => 'في الانتظار',
    'confirmed' => 'مؤكد',
    'processing' => 'قيد المعالجة',
    'shipped' => 'تم الشحن',
    'delivered' => 'تم التسليم',
    'cancelled' => 'ملغي',
    'completed' => 'مكتمل',
    'reserved' => 'محجوز',
    'available' => 'متاح',
    'low' => 'منخفض',
    'critical' => 'حرج',

    // Product Enhanced
    'product_name' => 'اسم المنتج',
    'product_code' => 'رمز المنتج',
    'sku' => 'رمز المنتج',
    'barcode' => 'الباركود',
    'description' => 'الوصف',
    'type' => 'النوع',
    'grammage' => 'الجراماج',
    'quality' => 'الجودة',
    'roll_number' => 'رقم اللفة',
    'source' => 'المصدر',
    'length' => 'الطول',
    'width' => 'العرض',
    'thickness' => 'السماكة',
    'dimensions' => 'الأبعاد',
    'specifications' => 'المواصفات',
    'material_cost' => 'تكلفة المواد',
    'price' => 'السعر',
    'purchase_price' => 'سعر الشراء',
    'selling_price' => 'سعر البيع',
    'wholesale_price' => 'سعر الجملة',
    'material_cost_per_ton' => 'تكلفة المواد للطن',
    'unit' => 'الوحدة',
    'weight' => 'الوزن',
    'volume' => 'الحجم',
    'area' => 'المساحة',
    'min_stock' => 'الحد الأدنى للمخزون',
    'max_stock' => 'الحد الأقصى للمخزون',
    'current_stock' => 'المخزون الحالي',
    'available_stock' => 'المخزون المتاح',
    'reserved_stock' => 'المخزون المحجوز',
    'reserved_weight' => 'الوزن المحجوز',
    'total_stock' => 'إجمالي المخزون',
    'stock_value' => 'قيمة المخزون',

    // Product Types
    'product_types' => [
        'roll' => 'لفة',
        'digma' => 'ديجما',
        'bale' => 'بالة',
        'sheet' => 'شريحة',
    ],

    // Quality Types
    'quality_types' => [
        'standard' => 'قياسي',
        'stock' => 'مخزون',
        'premium' => 'ممتاز',
    ],

    // Category
    'category' => 'الفئة',
    'category_name' => 'اسم الفئة',

    // Supplier
    'supplier' => 'المورد',
    'supplier_name' => 'اسم المورد',
    'contact_person' => 'الشخص المسؤول',
    'phone' => 'الهاتف',
    'email' => 'البريد الإلكتروني',
    'address' => 'العنوان',
    'tax_number' => 'الرقم الضريبي',
    'commercial_register' => 'السجل التجاري',
    'credit_limit' => 'الحد الائتماني',
    'payment_terms' => 'شروط الدفع',

    // Warehouse Types
    'warehouse_types' => [
        'main' => 'مستودع رئيسي',
        'scrap' => 'مستودع خردة',
        'sorting' => 'مستودع فرز',
        'custody' => 'مستودع حضانة',
    ],

    // Warehouse
    'warehouse' => 'المستودع',
    'warehouse_name' => 'اسم المستودع',
    'warehouse_code' => 'رمز المستودع',
    'manager' => 'المدير',
    'capacity' => 'السعة',
    'utilization' => 'معدل الاستخدام',
    'total_capacity' => 'إجمالي السعة',
    'used_capacity' => 'السعة المستخدمة',
    'available_capacity' => 'السعة المتاحة',
    'reserved_capacity' => 'السعة المحجوزة',
    'accepts_transfers' => 'يقبل التحويلات',
    'requires_approval' => 'يتطلب موافقة',

    // Customer
    'customer' => 'العميل',
    'customer_name' => 'اسم العميل',
    'province' => 'المحافظة',
    'mobile_number' => 'رقم الهاتف',
    'follow_up_person' => 'شخص المتابعة',
    'customer_type' => 'نوع العميل',
    'customer_types' => [
        'individual' => 'فردي',
        'company' => 'شركة',
    ],
    'total_orders' => 'إجمالي الطلبات',
    'total_paid' => 'إجمالي المدفوعات',
    'outstanding_amount' => 'المبلغ المستحق',

    // Order
    'order' => 'الطلب',
    'order_number' => 'رقم الطلب',
    'order_type' => 'نوع الطلب',
    'order_types' => [
        'in' => 'طلب شراء',
        'out' => 'طلب بيع',
    ],
    'purchase_order' => 'طلب شراء',
    'sales_order' => 'طلب بيع',
    'order_date' => 'تاريخ الطلب',
    'required_date' => 'التاريخ المطلوب',
    'shipped_date' => 'تاريخ الشحن',
    'customer_info' => 'معلومات العميل',
    'subtotal' => 'المجموع الفرعي',
    'tax' => 'الضريبة',
    'tax_amount' => 'مبلغ الضريبة',
    'discount' => 'الخصم',
    'discount_amount' => 'مبلغ الخصم',
    'shipping' => 'الشحن',
    'shipping_cost' => 'تكلفة الشحن',
    'total' => 'الإجمالي',
    'total_amount' => 'إجمالي المبلغ',
    'tracking_number' => 'رقم التتبع',
    'is_paid' => 'مدفوع',
    'paid_at' => 'تاريخ الدفع',
    'notes' => 'ملاحظات',
    'number_of_plates' => 'عدد الطبقات',
    'cutting_fees' => 'رسوم القص',
    'cutting_fees_per_ton' => 'رسوم القص للطن',
    'price_per_ton' => 'السعر للطن',
    'requested_weight' => 'الوزن المطلوب',
    'output_weight' => 'الوزن الناتج',
    'delivered_weight' => 'الوزن المسلم',
    'waste_weight' => 'وزن النفايات',
    'waste_percentage' => 'نسبة النفايات',
    'requester_name' => 'اسم طالب الطلب',
    'delivery_destination' => 'جهة التسليم',
    'receiving_destination' => 'جهة الاستلام',

    // Work Stages
    'work_stages' => [
        'sorting' => 'مرحلة الفرز',
        'cutting' => 'مرحلة القص',
        'delivery' => 'مرحلة التسليم',
    ],
    'stage' => 'المرحلة',
    'current_stage' => 'المرحلة الحالية',
    'next_stage' => 'المرحلة التالية',
    'stage_approval' => 'موافقة المرحلة',
    'sender_approval' => 'موافقة المرسل',
    'receiver_approval' => 'موافقة المستلم',

    // Waste
    'waste' => 'النفايات',
    'waste_type' => 'نوع النفايات',
    'waste_weight' => 'وزن النفايات',
    'waste_percentage' => 'نسبة النفايات',
    'waste_impact' => 'تأثير النفايات على السعر',

    // Transfer
    'transfer' => 'التحويل',
    'transfer_from' => 'التحويل من',
    'transfer_to' => 'التحويل إلى',
    'transfer_date' => 'تاريخ التحويل',
    'transfer_quantity' => 'كمية التحويل',
    'transfer_reason' => 'سبب التحويل',

    // Invoice
    'invoice' => 'الفاتورة',
    'invoice_number' => 'رقم الفاتورة',
    'invoice_date' => 'تاريخ الفاتورة',
    'due_date' => 'تاريخ الاستحقاق',
    'payment_status' => 'حالة الدفع',
    'payment_date' => 'تاريخ الدفع',

    // Stock Alerts
    'stock_alerts' => 'تنبيهات المخزون',
    'low_stock' => 'انخفاض المخزون',
    'out_of_stock' => 'نفاد المخزون',
    'expiring_soon' => 'ينتهي قريباً',
    'expired' => 'منتهي الصلاحية',
    'overstock' => 'زيادة المخزون',
    'alert_type' => 'نوع التنبيه',
    'alert_severity' => 'شدة التنبيه',
    'threshold_quantity' => 'الحد المسموح',
    'current_quantity' => 'الكمية الحالية',
    'is_read' => 'مقروء',
    'is_resolved' => 'تم الحل',
    'resolved_at' => 'تاريخ الحل',

    // Severity Levels
    'severity' => [
        'low' => 'منخفض',
        'medium' => 'متوسط',
        'high' => 'عالي',
        'critical' => 'حرج',
    ],

    // Units
    'units' => [
        'kg' => 'كيلوغرام',
        'ton' => 'طن',
        'piece' => 'قطعة',
        'meter' => 'متر',
        'cm' => 'سنتيمتر',
        'mm' => 'مليمتر',
        'liter' => 'لتر',
        'g' => 'غرام',
        'gsm' => 'غرام/متر مربع',
    ],

    // Messages
    'created_successfully' => 'تم الإنشاء بنجاح',
    'updated_successfully' => 'تم التحديث بنجاح',
    'deleted_successfully' => 'تم الحذف بنجاح',
    'error_occurred' => 'حدث خطأ',
    'no_data_found' => 'لا توجد بيانات',
    'confirm_delete' => 'هل أنت متأكد من الحذف؟',
    'confirm_approve' => 'هل أنت متأكد من الموافقة؟',
    'confirm_reject' => 'هل أنت متأكد من الرفض؟',
    'insufficient_stock' => 'المخزون غير كافي',
    'weight_balance_error' => 'خطأ في توازن الأوزان',
    'stage_approval_required' => 'مطلوب موافقة المرحلة',
    'delivery_authorization_required' => 'مطلوب تفويض التسليم',

    // Navigation
    'home' => 'الرئيسية',
    'profile' => 'الملف الشخصي',
    'logout' => 'تسجيل الخروج',
    'login' => 'تسجيل الدخول',
    'register' => 'التسجيل',

    // Company
    'company_name' => 'شركة الشرق الأوسط',
    'company_name_en' => 'Middle East Company',
    'company_slogan' => 'نظام إدارة المستودعات المتقدم',
    'copyright' => 'جميع الحقوق محفوظة',

    // API
    'api' => 'واجهة برمجة التطبيقات',
    'api_documentation' => 'توثيق API',
    'api_key' => 'مفتاح API',
    'api_rate_limit' => 'حد معدل الطلبات',

    // Real-time
    'real_time' => 'الوقت الفعلي',
    'live_updates' => 'التحديثات المباشرة',
    'notifications' => 'الإشعارات',
    'alerts' => 'التنبيهات',

    // Charts & Analytics
    'charts' => 'الرسوم البيانية',
    'analytics' => 'التحليلات',
    'statistics' => 'الإحصائيات',
    'trends' => 'الاتجاهات',
    'performance' => 'الأداء',
    'efficiency' => 'الكفاءة',

    // Drag & Drop
    'drag_drop' => 'سحب وإفلات',
    'drag_items_here' => 'اسحب العناصر إلى هنا',
    'drop_here' => 'أفلت هنا',
    'reorder' => 'إعادة ترتيب',
    'move' => 'نقل',

    // Colors & Themes
    'theme' => 'المظهر',
    'light_mode' => 'المظهر الفاتح',
    'dark_mode' => 'المظهر الداكن',
    'color_scheme' => 'نظام الألوان',
    'primary_color' => 'اللون الأساسي',
    'secondary_color' => 'اللون الثانوي',
    'accent_color' => 'لون التمييز',

    // Interactive Elements
    'interactive' => 'تفاعلي',
    'animations' => 'الرسوم المتحركة',
    'transitions' => 'الانتقالات',
    'hover_effects' => 'تأثيرات التمرير',
    'loading' => 'جاري التحميل',
    'progress' => 'التقدم',

    // Export & Import
    'export_pdf' => 'تصدير PDF',
    'export_excel' => 'تصدير Excel',
    'export_csv' => 'تصدير CSV',
    'import_csv' => 'استيراد CSV',
    'download' => 'تحميل',
    'upload' => 'رفع',

    // Validation
    'required' => 'مطلوب',
    'optional' => 'اختياري',
    'invalid' => 'غير صالح',
    'valid' => 'صالح',
    'minimum' => 'الحد الأدنى',
    'maximum' => 'الحد الأقصى',

    // Time & Date
    'date' => 'التاريخ',
    'time' => 'الوقت',
    'datetime' => 'التاريخ والوقت',
    'created_at' => 'تاريخ الإنشاء',
    'updated_at' => 'تاريخ التحديث',
    'expires_at' => 'تاريخ الانتهاء',

    // Financial
    'currency' => 'العملة',
    'amount' => 'المبلغ',
    'value' => 'القيمة',
    'cost' => 'التكلفة',
    'profit' => 'الربح',
    'loss' => 'الخسارة',
    'margin' => 'الهامش',
    'percentage' => 'النسبة المئوية',

    // Communication
    'message' => 'الرسالة',
    'comment' => 'التعليق',
    'feedback' => 'الملاحظات',
    'support' => 'الدعم',
    'contact' => 'التواصل',

    // Success & Error
    'success' => 'نجح',
    'error' => 'خطأ',
    'warning' => 'تحذير',
    'info' => 'معلومات',
    'notice' => 'إشعار',

    // Buttons
    'submit' => 'إرسال',
    'reset' => 'إعادة تعيين',
    'clear' => 'مسح',
    'close' => 'إغلاق',
    'back' => 'رجوع',
    'next' => 'التالي',
    'previous' => 'السابق',
    'finish' => 'إنهاء',
    'continue' => 'متابعة',

    // Tables
    'table' => 'جدول',
    'rows' => 'صفوف',
    'columns' => 'أعمدة',
    'pagination' => 'ترقيم الصفحات',
    'per_page' => 'لكل صفحة',
    'showing' => 'عرض',
    'of' => 'من',
    'results' => 'نتائج',

    // Forms
    'form' => 'نموذج',
    'field' => 'حقل',
    'label' => 'تسمية',
    'placeholder' => 'نص توضيحي',
    'help' => 'مساعدة',
    'tooltip' => 'تلميح',

    // Accessibility
    'accessibility' => 'إمكانية الوصول',
    'screen_reader' => 'قارئ الشاشة',
    'keyboard_navigation' => 'التنقل بالمفاتيح',
    'high_contrast' => 'تباين عالي',
    'large_text' => 'نص كبير',

    // Performance
    'performance' => 'الأداء',
    'speed' => 'السرعة',
    'optimization' => 'التحسين',
    'caching' => 'التخزين المؤقت',
    'lazy_loading' => 'التحميل الكسول',

    // Security
    'security' => 'الأمان',
    'authentication' => 'المصادقة',
    'authorization' => 'التفويض',
    'encryption' => 'التشفير',
    'permissions' => 'الصلاحيات',

    // Maintenance
    'maintenance' => 'الصيانة',
    'backup' => 'النسخ الاحتياطي',
    'restore' => 'الاستعادة',
    'update' => 'التحديث',
    'upgrade' => 'الترقية',
];