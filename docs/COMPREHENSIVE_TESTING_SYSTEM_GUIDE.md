# نظام الاختبار الشامل - دليل المستخدم

## نظرة عامة

يوفر هذا النظام مجموعة شاملة من الاختبارات لاختبار جميع جوانب النظام:

### مكونات النظام

#### 1. Performance Testing Suite
- **اختبارات الحمولة (LoadTest)**: اختبار الأداء تحت حمولة عالية
- **اختبارات الذاكرة (MemoryUsageTest)**: فحص استخدام الذاكرة وتسريباتها
- **اختبارات قاعدة البيانات (DatabasePerformanceTest)**: أداء الاستعلامات والعمليات
- **اختبارات أوقات الاستجابة (ResponseTimeTest)**: سرعة استجابة النظام
- **اختبارات المستخدمين المتزامنين (ConcurrentUserTest)**: الأداء مع عدة مستخدمين
- **اختبارات الإجهاد (StressTest)**: اختبار النظام تحت ظروف قاسية

#### 2. Security Testing Suite
- **اختبارات المصادقة (AuthenticationSecurityTest)**: أمان تسجيل الدخول والكلمات السرية
- **اختبارات أمان قاعدة البيانات (DatabaseSecurityTest)**: حماية البيانات من SQL Injection

#### 3. Integration Testing Suite
- **اختبارات التكامل**: فحص تفاعل المكونات المختلفة

#### 4. Functional Testing Suite
- **اختبارات الوظائف**: فحص المهام الأساسية للنظام

#### 5. API Testing Suite
- **اختبارات واجهة برمجة التطبيقات**: أمان وأداء APIs

#### 6. Database Testing Suite
- **اختبارات قاعدة البيانات**: migrations وperformance

#### 7. Monitoring System Tests
- **اختبارات المراقبة**: فحص أنظمة المراقبة والتنبيهات

#### 8. System Integration Test
- **اختبار التكامل الشامل**: اختبار النظام من البداية إلى النهاية

## متطلبات التشغيل

### متطلبات النظام
- PHP 8.0+
- Laravel 9+
- MySQL 8.0+
- Composer
- Redis (اختياري للـ cache)

### التبعيات المطلوبة
```json
{
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "orchestra/testbench": "^7.0"
    }
}
```

## كيفية التشغيل

### تشغيل جميع الاختبارات
```bash
# تشغيل السكريبت الشامل
bash scripts/comprehensive_test_suite.sh

# أو تشغيل يدوياً
php artisan test
```

### تشغيل مجموعة اختبارات محددة
```bash
# اختبارات الأداء فقط
php artisan test tests/Performance/

# اختبارات الأمان فقط
php artisan test tests/Security/

# اختبارات API فقط
php artisan test tests/Api/
```

### تشغيل اختبار واحد
```bash
php artisan test tests/Performance/LoadTest.php
php artisan test tests/Security/AuthenticationSecurityTest.php
```

## التكوين

### متغيرات البيئة
```env
# إعدادات الأمان
SECURITY_AUTH_ATTEMPTS=5
SECURITY_LOCKOUT_DURATION=900
SECURITY_MAX_CONCURRENT_SESSIONS=3

# إعدادات الأداء
PERFORMANCE_LOAD_THRESHOLD=500
PERFORMANCE_MEMORY_LIMIT=100MB
PERFORMANCE_RESPONSE_TIME_LIMIT=1000ms

# إعدادات قاعدة البيانات
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=test_db
DB_USERNAME=test_user
DB_PASSWORD=test_pass
```

### إعدادات PHPUnit
```xml
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Performance">
            <directory>tests/Performance</directory>
        </testsuite>
        <testsuite name="Security">
            <directory>tests/Security</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## التقارير والتحليل

### أنواع التقارير
1. **تقارير HTML**: تقارير مرئية مع الرسوم البيانية
2. **تقارير JSON**: بيانات منظمة للتحليل
3. **تقارير CSV**: جداول للتحليل في Excel
4. **التقارير التنفيذية**: ملخص للمدراء

### مسار حفظ التقارير
```
storage/app/test-reports/
├── html/
│   ├── comprehensive_test_report_2024-01-01.html
│   └── performance_report_2024-01-01.html
├── json/
│   ├── test_report_2024-01-01.json
│   └── security_report_2024-01-01.json
└── csv/
    ├── performance_results_2024-01-01.csv
    └── security_results_2024-01-01.csv
```

## تحليل النتائج

### مؤشرات الأداء الرئيسية

#### اختبارات الأداء
- **معدل الاستجابة**: يجب أن يكون < 500ms
- **معالجة الطلبات**: يجب أن تكون > 100 طلب/ثانية
- **استهلاك الذاكرة**: يجب أن يكون < 100MB
- **معدل نجاح المستخدمين المتزامنين**: يجب أن يكون > 95%

#### اختبارات الأمان
- **حماية SQL Injection**: 100% حماية مطلوبة
- **حماية XSS**: 100% حماية مطلوبة
- **إدارة الجلسات**: آمنة ومنضبطة
- **سياسات كلمات المرور**: قوية ومطبقة

#### اختبارات قاعدة البيانات
- **زمن الاستعلام**: يجب أن يكون < 100ms للاستعلامات البسيطة
- **النزاهة المرجعية**: يجب الحفاظ عليها
- **الأمان**: 100% من الحساسية
- **النسخ الاحتياطية**: تلقائية ومحمية

## استكشاف الأخطاء

### المشاكل الشائعة

#### اختبارات الأداء
```bash
# مشكلة بطء الاستجابة
# الحل: تحسين الفهارس والاستعلامات
php artisan migrate --force
php artisan db:seed

# مشكلة استهلاك الذاكرة
# الحل: تحسين lazy loading وcaching
php artisan cache:clear
```

#### اختبارات الأمان
```bash
# مشكلة رفض المصادقة
# الحل: التحقق من إعدادات session
php artisan config:clear
php artisan cache:clear

# مشكلة حماية SQL
# الحل: التحقق من parameterized queries
php artisan migrate:fresh --seed
```

#### اختبارات قاعدة البيانات
```bash
# مشكلة الاتصالات
# الحل: التحقق من إعدادات قاعدة البيانات
php artisan migrate:status
php artisan db:seed
```

## التخصيص والتطوير

### إضافة اختبار جديد
```php
<?php
namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_custom_performance_metric()
    {
        // كود الاختبار
        $this->assertTrue(true);
    }
}
```

### تخصيص التقارير
```php
use Tests\Report\TestReportGenerator;

$reporter = new TestReportGenerator();
$reporter->addTestResult('Custom Test', 'passed', 150);
$htmlReport = $reporter->generateHtmlReport('my_custom_report');
```

## أفضل الممارسات

### 1. كتابة الاختبارات
- اجعل كل اختبار مستقلاً
- استخدم أسماء واضحة ومعبرة
- تجنب الاختبارات المجمعة
- اختبر الحالات الاستثنائية

### 2. إدارة البيانات
- استخدم factories وseeders
- نظف البيانات بعد كل اختبار
- تجنب الاعتماد على بيانات حقيقية

### 3. الأداء
- شغل الاختبارات في بيئة معزولة
- استخدم caching بحكمة
- راقب استهلاك الذاكرة

### 4. الأمان
- لا تختبر في بيئة الإنتاج
- استخدم بيانات وهمية
- لا تكشف معلومات حساسة في التقارير

## المراقبة والصيانة

### جدولة الاختبارات
```bash
# تشغيل يومي
0 2 * * * /path/to/comprehensive_test_suite.sh

# تشغيل أسبوعي شامل
0 1 * * 0 /path/to/comprehensive_test_suite.sh --full
```

### مراقبة النتائج
- راجع التقارير اليومية
- تتبع المؤشرات الرئيسية
- عين على الانحرافات

### الصيانة
- حدث التبعيات بانتظام
- راجع وأصلح الاختبارات الفاشلة
- حسّن الأداء باستمرار

## الدعم والمساعدة

### الموارد
- [دليل Laravel Testing](https://laravel.com/docs/testing)
- [دليل PHPUnit](https://phpunit.de/documentation.html)
- [دليل Laravel Performance](https://laravel.com/docs/queues)

### للحصول على المساعدة
1. راجع الوثائق أولاً
2. تحقق من سجلات الأخطاء
3. شغل الاختبارات بشكل منفرد
4. راجع التقارير المفصلة

---

**ملاحظة**: هذا النظام مصمم ليكون شاملاً ومرناً. يمكن تخصيصه حسب احتياجات مشروعك المحددة.