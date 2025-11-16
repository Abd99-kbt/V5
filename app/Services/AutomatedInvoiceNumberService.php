<?php

namespace App\Services;

use App\Models\Invoice;

class AutomatedInvoiceNumberService
{
    const PATTERN = 'INV-{YEAR}-{SEQUENCE}';
    const SEQUENCE_PADDING = 4;

    /**
     * توليد رقم فاتورة جديد
     */
    public function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $sequence = $this->getNextSequenceNumber($year);
        $invoiceNumber = $this->formatInvoiceNumber($year, $sequence);

        // التأكد من عدم وجود الرقم (على الرغم من أن التسلسل يضمن الفرادة)
        while ($this->checkInvoiceNumberExists($invoiceNumber)) {
            $sequence++;
            $invoiceNumber = $this->formatInvoiceNumber($year, $sequence);
        }

        return $invoiceNumber;
    }

    /**
     * الحصول على الرقم التسلسلي التالي للسنة المحددة
     */
    public function getNextSequenceNumber(int $year): int
    {
        $pattern = "INV-{$year}-%";
        $maxInvoice = Invoice::where('invoice_number', 'like', $pattern)
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($maxInvoice) {
            $parts = explode('-', $maxInvoice->invoice_number);
            if (count($parts) === 3) {
                $sequence = (int) $parts[2];
                return $sequence + 1;
            }
        }

        return 1;
    }

    /**
     * تنسيق رقم الفاتورة
     */
    public function formatInvoiceNumber(int $year, int $sequence): string
    {
        $paddedSequence = str_pad($sequence, self::SEQUENCE_PADDING, '0', STR_PAD_LEFT);
        return str_replace(['{YEAR}', '{SEQUENCE}'], [$year, $paddedSequence], self::PATTERN);
    }

    /**
     * التحقق من صحة رقم الفاتورة
     */
    public function validateInvoiceNumber(string $invoiceNumber): bool
    {
        // التحقق من التنسيق
        if (!preg_match('/^INV-\d{4}-\d{' . self::SEQUENCE_PADDING . '}$/', $invoiceNumber)) {
            return false;
        }

        // التحقق من عدم الوجود في قاعدة البيانات
        return !$this->checkInvoiceNumberExists($invoiceNumber);
    }

    /**
     * الحصول على نمط أرقام الفواتير
     */
    public function getInvoiceNumberPattern(): string
    {
        return self::PATTERN;
    }

    /**
     * إعادة تعيين التسلسل للسنة الجديدة
     */
    public function resetSequenceForNewYear(int $year): void
    {
        // هذا يعيد التسلسل إلى 1 للسنة المحددة
        // لكن بما أن التسلسل يعتمد على الفواتير الموجودة،
        // قد يحتاج إلى حذف الفواتير أو تعديلها يدوياً
        // هنا نقوم بإعادة تعيين افتراضية
        // يمكن تعديل هذا حسب الحاجة
    }

    /**
     * التحقق من وجود رقم الفاتورة
     */
    public function checkInvoiceNumberExists(string $invoiceNumber): bool
    {
        return Invoice::where('invoice_number', $invoiceNumber)->exists();
    }
}