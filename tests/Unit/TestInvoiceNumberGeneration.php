<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Services\AutomatedInvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class TestInvoiceNumberGeneration extends TestCase
{
    use RefreshDatabase;

    private AutomatedInvoiceNumberService $service;
    private array $testResults = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AutomatedInvoiceNumberService();
        $this->testResults = [];
    }

    /**
     * تشغيل جميع الاختبارات وإنشاء تقرير مفصل
     */
    public function testRunAllInvoiceNumberTests()
    {
        $this->logTestStart('بدء اختبارات توليد أرقام الفواتير');

        $this->testGenerateNewInvoiceNumber();
        $this->testUniquenessCheck();
        $this->testFormatValidation();
        $this->testAnnualSequence();
        $this->testNewYearReset();
        $this->testNumberValidation();
        $this->testPerformance();

        $this->generateDetailedReport();
    }

    /**
     * اختبار توليد رقم فاتورة جديد
     */
    private function testGenerateNewInvoiceNumber()
    {
        $this->logTestStart('اختبار توليد رقم فاتورة جديد');

        try {
            $invoiceNumber = $this->service->generateInvoiceNumber();

            $this->assertNotEmpty($invoiceNumber, 'يجب أن يتم توليد رقم فاتورة');
            $this->assertIsString($invoiceNumber, 'يجب أن يكون رقم الفاتورة نصاً');

            // التحقق من التنسيق
            $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{4}$/', $invoiceNumber,
                'يجب أن يتبع رقم الفاتورة التنسيق الصحيح');

            $this->logTestResult('نجح', 'تم توليد رقم فاتورة جديد: ' . $invoiceNumber);
        } catch (\Exception $e) {
            $this->logTestResult('فشل', 'خطأ في توليد رقم الفاتورة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار التحقق من عدم التكرار
     */
    private function testUniquenessCheck()
    {
        $this->logTestStart('اختبار التحقق من عدم التكرار');

        try {
            // إنشاء عميل أولاً
            $customer = \App\Models\Customer::create([
                'name_en' => 'Test Customer',
                'name_ar' => 'عميل تجريبي',
                'province_en' => 'Test Province',
                'province_ar' => 'محافظة تجريبية',
                'mobile_number' => '1234567890',
                'follow_up_person_en' => 'Test Person',
                'follow_up_person_ar' => 'شخص تجريبي',
                'credit_limit' => 1000.00,
                'customer_type' => 'individual',
                'is_active' => true,
            ]);

            // إنشاء فاتورة موجودة
            $existingNumber = 'INV-2025-0001';
            Invoice::create([
                'customer_id' => $customer->id,
                'invoice_number' => $existingNumber,
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'subtotal' => 100.00,
                'tax_amount' => 10.00,
                'discount_amount' => 0.00,
                'total_amount' => 110.00,
                'is_paid' => false,
            ]);

            // توليد رقم جديد
            $newNumber = $this->service->generateInvoiceNumber();

            $this->assertNotEquals($existingNumber, $newNumber,
                'يجب ألا يتكرر رقم الفاتورة');

            $this->assertFalse($this->service->checkInvoiceNumberExists($newNumber),
                'يجب ألا يوجد الرقم الجديد في قاعدة البيانات');

            $this->logTestResult('نجح', 'تم التحقق من عدم تكرار الأرقام');
        } catch (\Exception $e) {
            $this->logTestResult('فشل', 'خطأ في التحقق من عدم التكرار: ' . $e->getMessage());
        }
    }

    /**
     * اختبار التحقق من التنسيق الصحيح
     */
    private function testFormatValidation()
    {
        $this->logTestStart('اختبار التحقق من التنسيق الصحيح');

        try {
            $year = 2025;
            $sequence = 1;

            $formattedNumber = $this->service->formatInvoiceNumber($year, $sequence);

            $this->assertEquals('INV-2025-0001', $formattedNumber,
                'يجب أن يتم تنسيق الرقم بشكل صحيح');

            // اختبار أرقام مختلفة
            $testCases = [
                [2025, 1, 'INV-2025-0001'],
                [2025, 100, 'INV-2025-0100'],
                [2025, 9999, 'INV-2025-9999'],
                [2024, 1, 'INV-2024-0001'],
            ];

            foreach ($testCases as [$testYear, $testSeq, $expected]) {
                $result = $this->service->formatInvoiceNumber($testYear, $testSeq);
                $this->assertEquals($expected, $result,
                    "فشل في تنسيق الرقم للسنة {$testYear} والتسلسل {$testSeq}");
            }

            $this->logTestResult('نجح', 'تم التحقق من صحة التنسيق');
        } catch (\Exception $e) {
            $this->logTestResult('فشل', 'خطأ في التحقق من التنسيق: ' . $e->getMessage());
        }
    }

    /**
     * اختبار التسلسل السنوي
     */
    private function testAnnualSequence()
    {
        $this->logTestStart('اختبار التسلسل السنوي');

        try {
            $year = 2025;

            // إنشاء عميل
            $customer = \App\Models\Customer::create([
                'name_en' => 'Test Customer 2',
                'name_ar' => 'عميل تجريبي 2',
                'province_en' => 'Test Province',
                'province_ar' => 'محافظة تجريبية',
                'mobile_number' => '1234567891',
                'follow_up_person_en' => 'Test Person',
                'follow_up_person_ar' => 'شخص تجريبي',
                'credit_limit' => 1000.00,
                'customer_type' => 'individual',
                'is_active' => true,
            ]);

            // إنشاء فواتير للسنة بأرقام مختلفة لتجنب التكرار
            $invoices = [
                'INV-2025-0001',
                'INV-2025-0002',
                'INV-2025-0005', // فجوة في التسلسل
            ];

            foreach ($invoices as $index => $number) {
                Invoice::create([
                    'customer_id' => $customer->id,
                    'invoice_number' => $number,
                    'invoice_date' => now()->subDays($index + 1), // تاريخ مختلف قليلاً
                    'due_date' => now()->addDays(30 - $index),
                    'subtotal' => 100.00,
                    'tax_amount' => 10.00,
                    'discount_amount' => 0.00,
                    'total_amount' => 110.00,
                    'is_paid' => false,
                ]);
            }

            $nextSequence = $this->service->getNextSequenceNumber($year);

            // يجب أن يكون التسلسل التالي 6 (أكبر من 5)
            $this->assertEquals(6, $nextSequence,
                'يجب أن يحسب التسلسل التالي بشكل صحيح');

            $this->logTestResult('نجح', 'تم التحقق من التسلسل السنوي');
        } catch (\Exception $e) {
            $this->logTestResult('فشل', 'خطأ في التسلسل السنوي: ' . $e->getMessage());
        }
    }

    /**
     * اختبار إعادة التعيين للسنة الجديدة
     */
    private function testNewYearReset()
    {
        $this->logTestStart('اختبار إعادة التعيين للسنة الجديدة');

        try {
            // إنشاء عميل
            $customer = \App\Models\Customer::create([
                'name_en' => 'Test Customer 3',
                'name_ar' => 'عميل تجريبي 3',
                'province_en' => 'Test Province',
                'province_ar' => 'محافظة تجريبية',
                'mobile_number' => '1234567892',
                'follow_up_person_en' => 'Test Person',
                'follow_up_person_ar' => 'شخص تجريبي',
                'credit_limit' => 1000.00,
                'customer_type' => 'individual',
                'is_active' => true,
            ]);

            // إنشاء فواتير لسنة 2024
            Invoice::create([
                'customer_id' => $customer->id,
                'invoice_number' => 'INV-2024-0001',
                'invoice_date' => Carbon::create(2024, 1, 1),
                'due_date' => Carbon::create(2024, 1, 31),
                'subtotal' => 100.00,
                'tax_amount' => 10.00,
                'discount_amount' => 0.00,
                'total_amount' => 110.00,
                'is_paid' => false,
            ]);

            // التحقق من أن التسلسل لسنة 2025 يبدأ من 1 (لا توجد فواتير لسنة 2025 بعد)
            $nextSequence2025 = $this->service->getNextSequenceNumber(2025);
            $this->assertEquals(1, $nextSequence2025,
                'يجب أن يبدأ التسلسل من 1 لسنة جديدة');

            // إنشاء فاتورة لسنة 2025
            Invoice::create([
                'customer_id' => $customer->id,
                'invoice_number' => 'INV-2025-0001',
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'subtotal' => 100.00,
                'tax_amount' => 10.00,
                'discount_amount' => 0.00,
                'total_amount' => 110.00,
                'is_paid' => false,
            ]);

            $nextSequence2025After = $this->service->getNextSequenceNumber(2025);
            $this->assertEquals(2, $nextSequence2025After,
                'يجب أن يستمر التسلسل لنفس السنة');

            $this->logTestResult('نجح', 'تم التحقق من إعادة التعيين للسنة الجديدة');
        } catch (\Exception $e) {
            $this->logTestResult('فشل', 'خطأ في إعادة التعيين للسنة الجديدة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار التحقق من صحة الأرقام
     */
    private function testNumberValidation()
    {
        $this->logTestStart('اختبار التحقق من صحة الأرقام');

        try {
            // أرقام صحيحة
            $validNumbers = [
                'INV-2025-0001',
                'INV-2024-9999',
                'INV-2023-0123',
            ];

            foreach ($validNumbers as $number) {
                $isValid = $this->service->validateInvoiceNumber($number);
                $this->assertTrue($isValid,
                    "يجب أن يكون الرقم {$number} صحيحاً");
            }

            // أرقام غير صحيحة
            $invalidNumbers = [
                'INV-2025-001',     // تسلسل قصير جداً
                'INV-2025-00001',   // تسلسل طويل جداً
                'INV-25-0001',      // سنة قصيرة
                'INV-20255-0001',   // سنة طويلة
                'INV-2025-ABCD',    // تسلسل غير رقمي
                'ABC-2025-0001',    // بادئة خاطئة
                'INV-2025-0001-extra', // إضافات غير مرغوبة
            ];

            foreach ($invalidNumbers as $number) {
                $isValid = $this->service->validateInvoiceNumber($number);
                $this->assertFalse($isValid,
                    "يجب أن يكون الرقم {$number} غير صحيح");
            }

            $this->logTestResult('نجح', 'تم التحقق من صحة الأرقام');
        } catch (\Exception $e) {
            $this->logTestResult('فشل', 'خطأ في التحقق من صحة الأرقام: ' . $e->getMessage());
        }
    }

    /**
     * اختبار الأداء والسرعة
     */
    private function testPerformance()
    {
        $this->logTestStart('اختبار الأداء والسرعة');

        try {
            $iterations = 100;
            $startTime = microtime(true);

            for ($i = 0; $i < $iterations; $i++) {
                $this->service->generateInvoiceNumber();
            }

            $endTime = microtime(true);
            $totalTime = $endTime - $startTime;
            $averageTime = $totalTime / $iterations;

            // يجب ألا يتجاوز المتوسط 0.01 ثانية لكل توليد
            $this->assertLessThan(0.01, $averageTime,
                'يجب أن يكون الأداء مقبولاً (أقل من 0.01 ثانية لكل توليد)');

            $this->logTestResult('نجح', sprintf(
                'تم توليد %d أرقام في %.4f ثانية (متوسط %.6f ثانية لكل رقم)',
                $iterations, $totalTime, $averageTime
            ));
        } catch (\Exception $e) {
            $this->logTestResult('فشل', 'خطأ في اختبار الأداء: ' . $e->getMessage());
        }
    }

    /**
     * تسجيل بدء الاختبار
     */
    private function logTestStart(string $testName)
    {
        $this->testResults[] = [
            'test' => $testName,
            'status' => 'running',
            'start_time' => now(),
            'message' => '',
        ];
    }

    /**
     * تسجيل نتيجة الاختبار
     */
    private function logTestResult(string $status, string $message)
    {
        $lastIndex = count($this->testResults) - 1;
        $this->testResults[$lastIndex]['status'] = $status;
        $this->testResults[$lastIndex]['end_time'] = now();
        $this->testResults[$lastIndex]['message'] = $message;
    }

    /**
     * إنشاء تقرير مفصل
     */
    private function generateDetailedReport()
    {
        $report = "\n" . str_repeat('=', 80) . "\n";
        $report .= "تقرير اختبارات توليد أرقام الفواتير\n";
        $report .= str_repeat('=', 80) . "\n\n";

        $passed = 0;
        $failed = 0;
        $totalTime = 0;

        foreach ($this->testResults as $result) {
            $status = $result['status'] === 'نجح' ? '✅ نجح' : '❌ فشل';
            $duration = isset($result['end_time']) && isset($result['start_time'])
                ? $result['end_time']->diffInMilliseconds($result['start_time']) . 'ms'
                : 'غير محدد';

            $report .= "الاختبار: {$result['test']}\n";
            $report .= "الحالة: {$status}\n";
            $report .= "الوقت المستغرق: {$duration}\n";
            $report .= "الرسالة: {$result['message']}\n";
            $report .= str_repeat('-', 50) . "\n";

            if ($result['status'] === 'نجح') {
                $passed++;
            } else {
                $failed++;
            }

            if (isset($result['end_time']) && isset($result['start_time'])) {
                $totalTime += $result['end_time']->diffInMilliseconds($result['start_time']);
            }
        }

        $report .= "\nملخص النتائج:\n";
        $report .= "الاختبارات المنجحة: {$passed}\n";
        $report .= "الاختبارات الفاشلة: {$failed}\n";
        $report .= "إجمالي الوقت: {$totalTime}ms\n";
        $report .= "معدل النجاح: " . round(($passed / ($passed + $failed)) * 100, 2) . "%\n";

        $report .= "\nمعلومات النظام:\n";
        $report .= "وقت التشغيل: " . now()->format('Y-m-d H:i:s') . "\n";
        $report .= "إصدار PHP: " . PHP_VERSION . "\n";
        $report .= "بيئة الاختبار: PHPUnit\n";

        $report .= str_repeat('=', 80) . "\n";

        // حفظ التقرير في ملف
        $reportFile = storage_path('logs/invoice_number_test_report_' . now()->format('Y-m-d_H-i-s') . '.txt');
        file_put_contents($reportFile, $report);

        echo $report;
        echo "\nتم حفظ التقرير في: {$reportFile}\n";
    }
}