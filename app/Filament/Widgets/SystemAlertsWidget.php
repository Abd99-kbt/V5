<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use App\Models\StockAlert;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;

class SystemAlertsWidget extends Widget
{

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getAlertsQuery())
            ->columns([
                TextColumn::make('type')
                    ->label('نوع التنبيه')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stock_low' => 'warning',
                        'order_delayed' => 'danger',
                        'quality_issue' => 'danger',
                        'maintenance' => 'gray',
                        'automation_error' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'stock_low' => 'مخزون منخفض',
                        'order_delayed' => 'تأخير في الطلب',
                        'quality_issue' => 'مشكلة جودة',
                        'maintenance' => 'صيانة مطلوبة',
                        'automation_error' => 'خطأ في الأتمتة',
                        default => $state,
                    }),

                TextColumn::make('title')
                    ->label('العنوان')
                    ->weight(FontWeight::Bold)
                    ->limit(50),

                TextColumn::make('description')
                    ->label('الوصف')
                    ->limit(100)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 100 ? $state : null;
                    }),

                TextColumn::make('severity')
                    ->label('الأولوية')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        'low' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('assigned_to')
                    ->label('مسؤول عنه')
                    ->placeholder('غير محدد'),
            ])
            ->actions([
                // Remove actions for now to avoid import issues
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->emptyStateHeading('لا توجد تنبيهات نشطة')
            ->emptyStateDescription('جميع الأنظمة تعمل بشكل طبيعي')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    protected function getAlertsQuery(): Builder
    {
        // Combine different types of alerts into a single query
        $stockAlerts = StockAlert::where('is_resolved', false)
            ->selectRaw("
                'stock_low' as type,
                CONCAT('تنبيه مخزون: ', products.name) as title,
                CONCAT('المخزون الحالي: ', CAST(available_quantity as CHAR), ', الحد الأدنى: ', CAST(min_stock_level as CHAR)) as description,
                CASE
                    WHEN available_quantity = 0 THEN 'critical'
                    WHEN available_quantity <= min_stock_level * 0.5 THEN 'high'
                    ELSE 'medium'
                END as severity,
                stock_alerts.created_at,
                NULL as assigned_to,
                stock_alerts.id as alert_id
            ")
            ->join('products', 'stock_alerts.product_id', '=', 'products.id')
            ->join('stocks', 'stock_alerts.stock_id', '=', 'stocks.id');

        $delayedOrders = Order::where('required_date', '<', now())
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->where('is_urgent', true)
            ->selectRaw("
                'order_delayed' as type,
                CONCAT('تأخير في الطلب: ', order_number) as title,
                CONCAT('الطلب متأخر منذ ', DATEDIFF(NOW(), required_date), ' يوم') as description,
                'high' as severity,
                created_at,
                NULL as assigned_to,
                id as alert_id
            ");

        $qualityIssues = \App\Models\Waste::where('created_at', '>=', now()->subDays(7))
            ->where('is_resolved', false)
            ->selectRaw("
                'quality_issue' as type,
                CONCAT('هدر عالي: ', products.name) as title,
                CONCAT('كمية الهدر: ', CAST(quantity as CHAR), ', السبب: ', reason) as description,
                CASE
                    WHEN quantity > 1000 THEN 'critical'
                    WHEN quantity > 500 THEN 'high'
                    ELSE 'medium'
                END as severity,
                wastes.created_at,
                NULL as assigned_to,
                wastes.id as alert_id
            ")
            ->join('products', 'wastes.product_id', '=', 'products.id');

        // Combine all alerts using union
        return $stockAlerts
            ->union($delayedOrders)
            ->union($qualityIssues)
            ->orderBy('created_at', 'desc')
            ->limit(20);
    }

    protected function resolveAlert(array $data): void
    {
        // Implementation for resolving alerts
        // This would update the relevant models based on alert type
        // For now, just show a notification
        \Filament\Notifications\Notification::make()
            ->title('تم حل التنبيه')
            ->body('تم حل التنبيه بنجاح')
            ->success()
            ->send();
    }

    protected function viewAlertDetails(array $data): void
    {
        // Implementation for viewing alert details
        // This could open a modal or redirect to relevant resource
        \Filament\Notifications\Notification::make()
            ->title('تفاصيل التنبيه')
            ->body('سيتم عرض التفاصيل قريباً')
            ->info()
            ->send();
    }
}