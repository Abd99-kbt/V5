<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Services\AutomatedReportingService;
use App\Services\AIPredictionService;
use Illuminate\Support\Facades\Cache;
use Filament\Support\Enums\FontWeight;

class EfficiencyMetricsWidget extends BaseWidget
{

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $reportingService = app(AutomatedReportingService::class);
        $aiService = app(AIPredictionService::class);

        // Get efficiency data with caching
        $efficiencyData = Cache::remember('dashboard_efficiency_metrics', 300, function () use ($reportingService, $aiService) {
            $efficiencyReport = $reportingService->generateEfficiencyReport(now()->subDays(7)->format('Y-m-d'), now()->format('Y-m-d'));
            $predictionData = $aiService->forecastDemand(7);

            return [
                'efficiency' => $efficiencyReport,
                'predictions' => $predictionData,
            ];
        });

        $efficiency = $efficiencyData['efficiency'];
        $predictions = $efficiencyData['predictions'];

        return [
            Stat::make('متوسط وقت المعالجة', $efficiency['summary']['average_processing_time_hours'] ?? 0 . ' ساعة')
                ->description('متوسط الوقت المطلوب لإنجاز الطلبات')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make('معدل التسليم في الوقت المحدد', ($efficiency['summary']['on_time_delivery_rate'] ?? 0) . '%')
                ->description('نسبة الطلبات المسلمة في الموعد')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($efficiency['summary']['on_time_delivery_rate'] ?? 0 >= 90 ? 'success' : 'warning'),

            Stat::make('الطلبات المتوقعة الأسبوع القادم', round($predictions['total_predicted_weight'] ?? 0, 1) . ' كجم')
                ->description('التنبؤ بالطلبات للأسبوع القادم')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Stat::make('كفاءة الأتمتة', $this->calculateAutomationEfficiency() . '%')
                ->description('نسبة العمليات الآلية مقابل اليدوية')
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('success'),

            Stat::make('معدل الإنتاج اليومي', $this->getDailyProductionRate() . ' كجم/ساعة')
                ->description('متوسط معدل الإنتاج في الساعة')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),

            Stat::make('مؤشر الأداء العام', $this->calculateOverallPerformanceIndex() . '%')
                ->description('مؤشر شامل لأداء النظام')
                ->descriptionIcon('heroicon-m-trophy')
                ->color($this->calculateOverallPerformanceIndex() >= 85 ? 'success' : 'warning'),
        ];
    }

    private function calculateAutomationEfficiency(): float
    {
        // Calculate based on automated processes vs total processes
        $automatedOrders = \App\Models\Order::where('is_automated', true)->count();
        $totalOrders = \App\Models\Order::count();

        if ($totalOrders === 0) return 0;

        return round(($automatedOrders / $totalOrders) * 100, 1);
    }

    private function getDailyProductionRate(): float
    {
        // Calculate average production rate per hour
        $todayProduction = \App\Models\Order::whereDate('completed_at', today())
            ->where('status', 'delivered')
            ->sum('delivered_weight');

        $workingHours = 8; // Assume 8 working hours per day

        if ($workingHours === 0) return 0;

        return round($todayProduction / $workingHours, 1);
    }

    private function calculateOverallPerformanceIndex(): float
    {
        // Composite index based on multiple factors
        $automationEfficiency = $this->calculateAutomationEfficiency();
        $qualityScore = $this->getAverageQualityScore();
        $onTimeDelivery = $this->getOnTimeDeliveryRate();

        // Weighted average: 40% automation, 30% quality, 30% delivery
        $index = ($automationEfficiency * 0.4) + ($qualityScore * 0.3) + ($onTimeDelivery * 0.3);

        return round($index, 1);
    }

    private function getAverageQualityScore(): float
    {
        return \App\Models\OrderProcessing::whereNotNull('quality_score')
            ->avg('quality_score') ?? 85.0;
    }

    private function getOnTimeDeliveryRate(): float
    {
        $deliveredOrders = \App\Models\Order::where('status', 'delivered')->count();
        $onTimeOrders = \App\Models\Order::where('status', 'delivered')
            ->whereColumn('shipped_date', '<=', 'required_date')
            ->count();

        if ($deliveredOrders === 0) return 0;

        return round(($onTimeOrders / $deliveredOrders) * 100, 1);
    }

    public function getDescription(): ?string
    {
        return 'مؤشرات الأداء والكفاءة للنظام الآلي مع تنبؤات ذكية';
    }
}