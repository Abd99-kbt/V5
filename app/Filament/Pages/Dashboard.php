<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\StockAlerts\StockAlertResource;
use App\Models\StockAlert;
use App\Models\Product;
use App\Models\Order;
use App\Models\Customer;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use App\Services\AutomatedReportingService;
use App\Services\IoTIntegrationService;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $title = 'لوحة التحكم';

    protected string $view = 'filament.pages.dashboard';

    protected ?string $pollingInterval = '30s';

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    public function getDescription(): ?string
    {
        return null;
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\StatsOverviewWidget::class,
            \App\Filament\Widgets\EfficiencyMetricsWidget::class,
            \App\Filament\Widgets\InventoryQualityWidget::class,
            \App\Filament\Widgets\SystemAlertsWidget::class,
            \App\Filament\Widgets\OperationsChartWidget::class,
            \App\Filament\Widgets\SalesChartWidget::class,
            \App\Filament\Widgets\OrderStageProgressWidget::class,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('new_order')
                    ->label('طلب جديد')
                    ->icon('heroicon-m-plus')
                    ->url(OrderResource::getUrl('create'))
                    ->color('primary'),

                Action::make('refresh_data')
                    ->label('تحديث البيانات')
                    ->icon('heroicon-m-arrow-path')
                    ->action(function () {
                        $this->refreshDashboardData();
                    })
                    ->color('info'),

                Action::make('low_stock_alerts')
                    ->label('تنبيهات المخزون المنخفض')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->action(function () {
                        $alerts = StockAlert::where('is_resolved', false)->get();
                        if ($alerts->isEmpty()) {
                            Notification::make()
                                ->title('لا توجد تنبيهات مخزون منخفض')
                                ->body('جميع مستويات المخزون طبيعية.')
                                ->success()
                                ->send();
                        } else {
                            return redirect()->to(StockAlertResource::getUrl('index'));
                        }
                    })
                    ->color('warning'),

                Action::make('system_reports')
                    ->label('التقارير الآلية')
                    ->icon('heroicon-m-document-chart-bar')
                    ->action(function () {
                        $reportingService = app(AutomatedReportingService::class);
                        $result = $reportingService->run();

                        if ($result['success']) {
                            Notification::make()
                                ->title('تم إنشاء التقارير')
                                ->body('تم إنشاء ' . count($result['reports_generated']) . ' تقرير تلقائياً')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('فشل في إنشاء التقارير')
                                ->body($result['error'] ?? 'حدث خطأ غير معروف')
                                ->danger()
                                ->send();
                        }
                    })
                    ->color('success'),
            ])
            ->label('إجراءات سريعة')
            ->icon('heroicon-m-bolt')
            ->color('gray'),
        ];
    }

    public function getTotalProducts(): int
    {
        return Product::count();
    }

    public function getTotalOrders(): int
    {
        return Order::count();
    }

    public function getTotalCustomers(): int
    {
        return Customer::count();
    }

    /**
     * Refresh dashboard data from services
     */
    public function refreshDashboardData(): void
    {
        // Trigger automated reporting
        $reportingService = app(AutomatedReportingService::class);
        $reportingService->run();

        // Update IoT data
        $iotService = app(IoTIntegrationService::class);
        $iotService->receiveRealTimeData();

        // Broadcast refresh event
        $this->dispatch('refreshDashboard');

        Notification::make()
            ->title('تم تحديث البيانات')
            ->body('تم تحديث جميع بيانات لوحة التحكم')
            ->success()
            ->send();
    }
}