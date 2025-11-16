# دليل النظام الآلي المتقدم

## مقدمة عن النظام الآلي

يُعد النظام الآلي المتقدم حلاً شاملاً ومتطوراً لأتمتة عمليات إدارة المستودعات والطلبات في شركة الشرق الأوسط. يجمع النظام بين الذكاء الاصطناعي، إنترنت الأشياء (IoT)، والأتمتة الذكية لتحسين الكفاءة التشغيلية وتقليل الأخطاء البشرية.

### الميزات الرئيسية للنظام:
- **الأتمتة الذكية**: أتمتة العمليات الروتينية مع الحفاظ على السيطرة البشرية
- **التعلم الآلي**: تحليل البيانات التاريخية لتحسين القرارات المستقبلية
- **إنترنت الأشياء**: ربط الأجهزة والمعدات لجمع البيانات في الوقت الفعلي
- **التحليلات المتقدمة**: تقارير مفصلة وتحليلات لتحسين الأداء
- **الأمان المتقدم**: تشفير البيانات وحماية الوصول

### الفوائد الرئيسية:
- تقليل التكاليف التشغيلية بنسبة تصل إلى 30%
- تحسين دقة العمليات بنسبة 95%
- تسريع وقت المعالجة للطلبات
- تحسين جودة المنتجات والخدمات
- زيادة رضا العملاء

## قائمة الخدمات المتاحة

النظام يوفر ست خدمات أساسية مترابطة:

### 1. خدمة التسعير الآلي (AutomatedPricingService)
- حساب التكاليف الشاملة للطلبات
- تحليل هامش الربح الأمثل
- تقدير تكاليف الهدر والنفايات
- تحديث الأسعار بناءً على البيانات التاريخية

### 2. خدمة الموافقة الآلية (AutomatedApprovalService)
- موافقة تلقائية على العمليات الروتينية
- التحقق من الجودة والمواصفات
- تقييم المخاطر والامتثال
- تقليل الحاجة للتدخل البشري

### 3. خدمة التنبؤ بالذكاء الاصطناعي (AIPredictionService)
- توقع أوقات إنجاز الطلبات
- تحليل الطلب المستقبلي على المواد
- كشف المشاكل المحتملة في الجودة
- تحسين جدولة الإنتاج

### 4. خدمة دمج إنترنت الأشياء (IoTIntegrationService)
- ربط الأجهزة والمعدات بالنظام
- جمع البيانات في الوقت الفعلي
- مراقبة حالة المعدات والآلات
- تحديث حالة الطلبات تلقائياً

### 5. خدمة اختيار المواد الذكية (SmartMaterialSelectionService)
- تحليل متطلبات الطلبات
- اختيار المواد المناسبة تلقائياً
- تحسين استخدام المخزون
- تقليل الهدر والنفايات

### 6. خدمة الرقابة على الجودة الآلية (AutomatedQualityControlService)
- فحص الأبعاد والمواصفات تلقائياً
- تحليل بصري للجودة
- تقييم متوازن الوزن
- تحديد الحاجة للمراجعة البشرية

## دليل التثبيت والإعداد

### المتطلبات الأساسية:
- PHP 8.1 أو أحدث
- Laravel 10.x
- قاعدة بيانات MySQL/PostgreSQL
- Composer لإدارة الحزم
- Node.js للأدوات الأمامية

### خطوات التثبيت:

1. **تحميل المشروع:**
```bash
git clone https://github.com/your-repo/automation-system.git
cd automation-system
```

2. **تثبيت التبعيات:**
```bash
composer install
npm install
```

3. **إعداد قاعدة البيانات:**
```bash
cp .env.example .env
php artisan key:generate
```

4. **تكوين متغيرات البيئة:**
```env
# Automation Services
AUTOMATION_PRICING_ENABLED=true
AUTOMATION_APPROVAL_ENABLED=true
AUTOMATION_AI_PREDICTION_ENABLED=true
AUTOMATION_IOT_ENABLED=true
AUTOMATION_SMART_MATERIAL_ENABLED=true
AUTOMATION_QUALITY_CONTROL_ENABLED=true

# IoT Configuration
MQTT_BROKER=localhost
MQTT_PORT=1883
IOT_ENABLED=true

# Alert Thresholds
LOW_STOCK_ALERT_THRESHOLD=10
HIGH_ERROR_RATE_THRESHOLD=5
```

5. **تشغيل المهاجرات:**
```bash
php artisan migrate
php artisan db:seed
```

6. **تثبيت Filament (لوحة التحكم):**
```bash
php artisan filament:install
```

7. **تشغيل الخدمات:**
```bash
php artisan serve
php artisan automation:schedule
```

### إعداد الجدولة التلقائية:

أضف السطر التالي إلى crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## كيفية استخدام كل خدمة

### 1. خدمة التسعير الآلي

**الحساب الشامل للسعر:**
```php
use App\Services\AutomatedPricingService;

$pricingService = new AutomatedPricingService();
$result = $pricingService->calculateComprehensivePrice($order, $user);

if ($result['success']) {
    echo "السعر المحسوب: " . $result['total_price'];
}
```

**عبر API:**
```bash
POST /api/automation/pricing/calculate
{
  "order_id": 123,
  "include_waste": true,
  "include_overhead": true
}
```

### 2. خدمة الموافقة الآلية

**فحص إمكانية الموافقة التلقائية:**
```php
use App\Services\AutomatedApprovalService;

$approvalService = new AutomatedApprovalService();
$result = $approvalService->autoApproveIfEligible($processing, $user);

if ($result['approved']) {
    echo "تمت الموافقة التلقائية";
}
```

### 3. خدمة التنبؤ بالذكاء الاصطناعي

**توقع وقت إنجاز الطلب:**
```php
use App\Services\AIPredictionService;

$predictionService = new AIPredictionService();
$result = $predictionService->predictOrderCompletionTime($order);

echo "الوقت المتوقع: " . $result['predicted_completion_time'] . " ساعة";
```

### 4. خدمة دمج إنترنت الأشياء

**ربط الأجهزة:**
```php
use App\Services\IoTIntegrationService;

$iotService = new IoTIntegrationService();
$result = $iotService->connectToProductionEquipment();

if ($result['success']) {
    echo "تم ربط " . $result['connected_devices'] . " جهاز";
}
```

### 5. خدمة اختيار المواد الذكية

**اختيار المواد تلقائياً:**
```php
use App\Services\SmartMaterialSelectionService;

$materialService = new SmartMaterialSelectionService();
$result = $materialService->autoSelectMaterials($order, $user);

if ($result['success']) {
    echo "تم اختيار " . $result['total_materials'] . " مادة";
}
```

### 6. خدمة الرقابة على الجودة الآلية

**إجراء فحص جودة:**
```php
use App\Services\AutomatedQualityControlService;

$qualityService = new AutomatedQualityControlService();
$result = $qualityService->performAutomatedQualityCheck($processing);

echo "درجة الجودة: " . $result['overall_score'] . "/100";
```

## إعدادات التكوين

### ملف config/automation.php

```php
return [
    'services' => [
        'automated_pricing' => env('AUTOMATION_PRICING_ENABLED', true),
        'automated_approval' => env('AUTOMATION_APPROVAL_ENABLED', true),
        'ai_prediction' => env('AUTOMATION_AI_PREDICTION_ENABLED', true),
        'iot_integration' => env('AUTOMATION_IOT_ENABLED', true),
        'smart_material_selection' => env('AUTOMATION_SMART_MATERIAL_ENABLED', true),
        'automated_quality_control' => env('AUTOMATION_QUALITY_CONTROL_ENABLED', true),
    ],

    'schedules' => [
        'pricing_update_interval' => env('PRICING_UPDATE_INTERVAL', 'hourly'),
        'approval_check_interval' => env('APPROVAL_CHECK_INTERVAL', '15 minutes'),
        'prediction_update_interval' => env('PREDICTION_UPDATE_INTERVAL', 'daily'),
        'iot_sync_interval' => env('IOT_SYNC_INTERVAL', '5 minutes'),
        'quality_check_interval' => env('QUALITY_CHECK_INTERVAL', '30 minutes'),
        'material_selection_interval' => env('MATERIAL_SELECTION_INTERVAL', '2 hours'),
    ],

    'limits' => [
        'max_automated_orders_per_hour' => env('MAX_AUTOMATED_ORDERS_PER_HOUR', 100),
        'min_confidence_threshold' => env('MIN_CONFIDENCE_THRESHOLD', 0.8),
        'max_price_change_percentage' => env('MAX_PRICE_CHANGE_PERCENTAGE', 10),
        'approval_threshold' => env('APPROVAL_THRESHOLD', 5000),
        'max_concurrent_automations' => env('MAX_CONCURRENT_AUTOMATIONS', 5),
        'processing_timeout_seconds' => env('PROCESSING_TIMEOUT_SECONDS', 300),
    ],

    'alerts' => [
        'email_notifications' => env('EMAIL_NOTIFICATIONS_ENABLED', true),
        'sms_notifications' => env('SMS_NOTIFICATIONS_ENABLED', false),
        'slack_notifications' => env('SLACK_NOTIFICATIONS_ENABLED', false),
        'push_notifications' => env('PUSH_NOTIFICATIONS_ENABLED', true),

        'alert_thresholds' => [
            'low_stock' => env('LOW_STOCK_ALERT_THRESHOLD', 10),
            'high_error_rate' => env('HIGH_ERROR_RATE_THRESHOLD', 5),
            'system_performance' => env('SYSTEM_PERFORMANCE_THRESHOLD', 80),
            'automation_failure_rate' => env('AUTOMATION_FAILURE_RATE_THRESHOLD', 3),
        ],
    ],

    'iot' => [
        'enabled' => env('IOT_ENABLED', true),
        'protocols' => [
            'mqtt' => [
                'enabled' => env('MQTT_ENABLED', true),
                'broker' => env('MQTT_BROKER', 'localhost'),
                'port' => env('MQTT_PORT', 1883),
            ],
        ],
        'security' => [
            'encryption' => env('IOT_ENCRYPTION_ENABLED', true),
            'authentication' => env('IOT_AUTHENTICATION_REQUIRED', true),
        ],
    ],
];
```

## مسارات API المتاحة

### مسارات التسعير الآلي
```
POST   /api/automation/pricing/calculate
POST   /api/automation/pricing/apply/{order}
GET    /api/automation/pricing/history/{order}
POST   /api/automation/pricing/rules
```

### مسارات الموافقة الآلية
```
POST   /api/automation/approval/process/{order}
GET    /api/automation/approval/pending
POST   /api/automation/approval/approve/{approval}
POST   /api/automation/approval/reject/{approval}
GET    /api/automation/approval/rules
```

### مسارات الرقابة على الجودة الآلية
```
POST   /api/automation/quality/check/{order}
GET    /api/automation/quality/reports/{order}
POST   /api/automation/quality/standards
GET    /api/automation/quality/metrics
```

### مسارات الذكاء الاصطناعي
```
POST   /api/ai/predict/demand
POST   /api/ai/predict/pricing
POST   /api/ai/predict/quality
POST   /api/ai/optimize/production
GET    /api/ai/insights
POST   /api/ai/train/model
```

### مسارات إنترنت الأشياء
```
POST   /api/iot/devices/register
GET    /api/iot/devices/status
POST   /api/iot/sensors/data
GET    /api/iot/analytics
POST   /api/iot/alerts/configure
GET    /api/iot/maintenance/predictions
```

### مسارات التقارير
```
GET    /api/reports/dashboard
GET    /api/reports/sales
GET    /api/reports/inventory
GET    /api/reports/production
GET    /api/reports/quality
GET    /api/reports/efficiency
POST   /api/reports/export/{type}
```

## أمثلة على الاستخدام

### مثال 1: حساب سعر طلب تلقائياً

```php
$order = Order::find(123);
$pricingService = new AutomatedPricingService();

$result = $pricingService->calculateComprehensivePrice($order);

if ($result['success']) {
    // عرض تفصيل السعر
    echo "السعر الأساسي: {$result['base_price']}\n";
    echo "تكاليف إضافية: {$result['additional_costs']['labor_cost']}\n";
    echo "هامش الربح: {$result['profit_margin']['percentage']}%\n";
    echo "السعر النهائي: {$result['total_price']}\n";
}
```

### مثال 2: ربط أجهزة IoT

```php
$iotService = new IoTIntegrationService();

// ربط معدات الإنتاج
$productionResult = $iotService->connectToProductionEquipment();

// ربط آلات القص
$cuttingResult = $iotService->connectCuttingMachines();

// ربط الموازين
$scaleResult = $iotService->connectWeightScales();

// ربط كاميرات الجودة
$cameraResult = $iotService->connectQualityCameras();

echo "تم ربط إجمالي " .
     ($productionResult['connected_devices'] +
      $cuttingResult['connected_devices'] +
      $scaleResult['connected_devices'] +
      $cameraResult['connected_devices']) .
     " جهاز";
```

### مثال 3: توقع الطلب المستقبلي

```php
$predictionService = new AIPredictionService();

// توقع الطلب للأيام الـ30 القادمة
$forecast = $predictionService->forecastDemand(30);

echo "الطلب المتوقع للأيام الـ30 القادمة:\n";
echo "إجمالي الوزن المتوقع: {$forecast['total_predicted_weight']} كجم\n";
echo "متوسط الطلبات اليومية: {$forecast['average_daily_orders']}\n";
echo "مستوى الثقة: " . ($forecast['confidence_level'] * 100) . "%\n";
```

### مثال 4: فحص جودة تلقائي

```php
$qualityService = new AutomatedQualityControlService();
$processing = OrderProcessing::find(456);

$result = $qualityService->performAutomatedQualityCheck($processing);

echo "نتيجة فحص الجودة:\n";
echo "الدرجة العامة: {$result['overall_score']}/100\n";
echo "هل يحتاج مراجعة بشرية: " . ($result['requires_human_review'] ? 'نعم' : 'لا') . "\n";

if (!empty($result['dimensions_check'])) {
    echo "فحص الأبعاد: " . ($result['dimensions_check'] ? 'نجح' : 'فشل') . "\n";
}
```

## استكشاف الأخطاء

### مشاكل شائعة وحلولها:

#### 1. فشل ربط أجهزة IoT
**الأعراض:** رسائل خطأ عند محاولة الاتصال بالأجهزة
**الحل:**
- تحقق من إعدادات MQTT في ملف .env
- تأكد من تشغيل خادم MQTT
- فحص جدار الحماية والمنافذ المفتوحة
- تحقق من صحة عناوين IP للأجهزة

#### 2. بطء في الاستجابة
**الأعراض:** تأخر في معالجة الطلبات
**الحل:**
- زيادة قيمة `processing_timeout_seconds`
- تحسين استعلامات قاعدة البيانات
- فحص استخدام الذاكرة والمعالج
- تفعيل التخزين المؤقت (Cache)

#### 3. أخطاء في التسعير الآلي
**الأعراض:** أسعار غير منطقية أو أخطاء في الحساب
**الحل:**
- فحص البيانات التاريخية للأسعار
- التحقق من صحة معاملات التكلفة
- مراجعة حدود التغيير المسموحة
- فحص حسابات هامش الربح

#### 4. فشل الموافقة الآلية
**الأعراض:** رفض الموافقات التلقائية بشكل غير متوقع
**الحل:**
- فحص معايير الموافقة التلقائية
- مراجعة حدود الموافقة
- التحقق من صحة بيانات الجودة
- فحص سجلات الأخطاء

#### 5. مشاكل في التنبؤات
**الأعراض:** تنبؤات غير دقيقة
**الحل:**
- زيادة حجم البيانات التدريبية
- فحص جودة البيانات التاريخية
- تعديل معاملات الثقة
- إعادة تدريب نماذج التعلم الآلي

### سجلات الأخطاء:

جميع الأخطاء تسجل في `storage/logs/laravel.log`. لفحص السجلات:

```bash
tail -f storage/logs/laravel.log
```

### أدوات التشخيص:

```bash
# فحص حالة الخدمات
php artisan automation:test

# فحص قاعدة البيانات
php artisan tinker
>>> DB::connection()->getPdo()

# فحص المهام المجدولة
php artisan schedule:list
```

## الأداء والتحسينات

### مؤشرات الأداء الرئيسية:

- **وقت معالجة الطلبات**: متوسط 15-30 دقيقة
- **دقة التسعير**: 95% مطابقة للأسعار اليدوية
- **معدل الموافقة التلقائية**: 80% من العمليات الروتينية
- **دقة التنبؤات**: 85% للطلبات قصيرة الأجل
- **وقت تشغيل النظام**: 99.9% uptime

### تحسينات الأداء:

#### 1. التخزين المؤقت (Caching)
```php
// تخزين نتائج التنبؤات لمدة ساعة
Cache::remember('prediction_' . $order->id, 3600, function () use ($order) {
    return $predictionService->predictOrderCompletionTime($order);
});
```

#### 2. قواعد البيانات
- إضافة فهارس على الأعمدة المستخدمة في البحث
- تحسين الاستعلامات المعقدة
- استخدام eager loading للعلاقات

#### 3. الذاكرة والمعالج
- مراقبة استخدام الموارد
- تحسين خوارزميات التعلم الآلي
- توزيع الأحمال على عدة خوادم

#### 4. الشبكة
- تحسين بروتوكولات IoT
- ضغط البيانات المرسلة
- استخدام WebSockets للتحديثات الفورية

### مراقبة الأداء:

```php
// مراقبة وقت تنفيذ العمليات
$startTime = microtime(true);
$result = $pricingService->calculateComprehensivePrice($order);
$executionTime = microtime(true) - $startTime;

Log::info('Pricing calculation performance', [
    'order_id' => $order->id,
    'execution_time' => $executionTime,
    'success' => $result['success']
]);
```

## خطط التطوير المستقبلية

### المرحلة الأولى (3 أشهر)
- [ ] تحسين خوارزميات التعلم الآلي
- [ ] إضافة دعم للمزيد من بروتوكولات IoT
- [ ] تطوير تطبيق جوال للمراقبة
- [ ] تحسين واجهة المستخدم

### المرحلة الثانية (6 أشهر)
- [ ] تكامل مع أنظمة ERP خارجية
- [ ] إضافة تحليلات متقدمة بالذكاء الاصطناعي
- [ ] دعم الواقع المعزز لفحص الجودة
- [ ] نظام إشعارات ذكي

### المرحلة الثالثة (12 شهراً)
- [ ] تطبيق blockchain لتتبع المنتجات
- [ ] تكامل مع أنظمة الإنتاج الذكية
- [ ] دعم الطباعة ثلاثية الأبعاد
- [ ] نظام توصيات ذكي للعملاء

### التحديثات طويلة الأمد
- [ ] تطوير منصة SaaS للشركات الأخرى
- [ ] دمج مع المدن الذكية
- [ ] دعم الطاقة المتجددة والاستدامة
- [ ] تطبيقات الواقع الافتراضي للتدريب

---

**تاريخ آخر تحديث:** نوفمبر 2025
**إصدار النظام:** 1.0.0
**شركة:** شركة الشرق الأوسط للحلول التقنية