<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Services\AutomatedReportingService;
use App\Services\AutomatedQualityControlService;
use Illuminate\Support\Facades\Cache;
use Filament\Support\Enums\FontWeight;

class InventoryQualityWidget extends BaseWidget
{

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $reportingService = app(AutomatedReportingService::class);
        $qualityService = app(AutomatedQualityControlService::class);

        // Get inventory and quality data with caching
        $inventoryData = Cache::remember('dashboard_inventory_quality', 600, function () use ($reportingService, $qualityService) {
            $wasteReport = $reportingService->generateWasteReport(now()->subDays(7)->format('Y-m-d'), now()->format('Y-m-d'));
            $qualityRun = $qualityService->run();

            return [
                'waste' => $wasteReport,
                'quality' => $qualityRun,
            ];
        });

        $waste = $inventoryData['waste'];
        $quality = $inventoryData['quality'];

        return [
            Stat::make('إجمالي الهدر', ($waste['summary']['total_waste'] ?? 0) . ' كجم')
                ->description('إجمالي الهدر في الأسبوع الماضي')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($waste['summary']['total_waste'] ?? 0 > 100 ? 'danger' : 'warning'),

            Stat::make('نسبة الهدر', ($waste['summary']['waste_ratio_percentage'] ?? 0) . '%')
                ->description('نسبة الهدر من الإنتاج الكلي')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($waste['summary']['waste_ratio_percentage'] ?? 0 > 10 ? 'danger' : 'success'),

            Stat::make('متوسط نقاط الجودة', ($quality['results'] ? collect($quality['results'])->avg('overall_score') : 0) . '%')
                ->description('متوسط نقاط فحص الجودة')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color(collect($quality['results'] ?? [])->avg('overall_score') >= 85 ? 'success' : 'warning'),

            Stat::make('فحوصات الجودة المكتملة', ($quality['processings_checked'] ?? 0))
                ->description('عدد فحوصات الجودة في اليوم')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('info'),

            Stat::make('المنتجات تحت الحد الأدنى', $this->getLowStockProductsCount())
                ->description('منتجات تحت مستوى المخزون الأدنى')
                ->descriptionIcon('heroicon-m-archive-box-x-mark')
                ->color($this->getLowStockProductsCount() > 0 ? 'danger' : 'success'),

            Stat::make('معدل دوران المخزون', $this->calculateInventoryTurnover() . ' مرات/شهر')
                ->description('معدل دوران المخزون الشهري')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary'),
        ];
    }

    private function getLowStockProductsCount(): int
    {
        return \App\Models\Product::where('is_active', true)
            ->where('track_inventory', true)
            ->whereHas('stocks', function($query) {
                $query->where('is_active', true)
                      ->whereRaw('quantity <= (SELECT min_stock_level FROM products WHERE id = product_id)');
            })
            ->count();
    }

    private function calculateInventoryTurnover(): float
    {
        // Calculate inventory turnover ratio
        $totalSales = \App\Models\Order::where('type', 'out')
            ->whereBetween('order_date', [now()->subMonth(), now()])
            ->sum('total_amount');

        $averageInventory = \App\Models\Stock::avg('quantity') ?? 1;

        if ($averageInventory == 0) return 0;

        // Simplified turnover calculation
        return round($totalSales / $averageInventory, 1);
    }

    public function getDescription(): ?string
    {
        return 'تقارير شاملة للمخزون ومؤشرات الجودة مع التنبيهات الآلية';
    }
}