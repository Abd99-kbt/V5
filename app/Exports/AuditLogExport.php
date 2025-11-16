<?php

namespace App\Exports;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AuditLogExport implements FromCollection, WithHeadings, WithMapping
{
    protected $query;

    public function __construct($query = null)
    {
        $this->query = $query;
    }

    public function collection(): Collection
    {
        return $this->query ? $this->query->get() : AuditLog::with(['user', 'auditable'])->get();
    }

    public function headings(): array
    {
        return [
            'التاريخ والوقت',
            'المستخدم',
            'نوع الحدث',
            'نوع السجل',
            'معرف السجل',
            'وصف الحدث',
            'عنوان IP',
            'المتصفح',
            'معرف الجلسة',
            'القيم السابقة',
            'القيم الجديدة',
            'البيانات الإضافية',
        ];
    }

    public function map($auditLog): array
    {
        return [
            $auditLog->created_at->format('Y-m-d H:i:s'),
            $auditLog->user?->name ?? 'غير محدد',
            $this->formatEventType($auditLog->event_type),
            class_basename($auditLog->auditable_type),
            $auditLog->auditable_id,
            $auditLog->event_description,
            $auditLog->ip_address,
            $auditLog->user_agent,
            $auditLog->session_id,
            $auditLog->old_values ? json_encode($auditLog->old_values, JSON_UNESCAPED_UNICODE) : '',
            $auditLog->new_values ? json_encode($auditLog->new_values, JSON_UNESCAPED_UNICODE) : '',
            $auditLog->metadata ? json_encode($auditLog->metadata, JSON_UNESCAPED_UNICODE) : '',
        ];
    }

    protected function formatEventType(string $eventType): string
    {
        return match($eventType) {
            'created' => 'إنشاء',
            'updated' => 'تحديث',
            'deleted' => 'حذف',
            'login' => 'دخول',
            'logout' => 'خروج',
            'viewed' => 'عرض',
            default => ucfirst($eventType)
        };
    }
}