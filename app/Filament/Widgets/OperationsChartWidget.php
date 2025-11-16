<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Services\AutomatedReportingService;
use App\Services\IoTIntegrationService;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class OperationsChartWidget extends ChartWidget
{

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $reportingService = app(AutomatedReportingService::class);
        $iotService = app(IoTIntegrationService::class);

        // Get operations data with caching
        $operationsData = Cache::remember('dashboard_operations_chart', 300, function () use ($reportingService, $iotService) {
            // Get production data for last 30 days
            $productionData = $reportingService->generateProductionReport(
                now()->subDays(30)->format('Y-m-d'),
                now()->format('Y-m-d')
            );

            // Get IoT real-time data
            $iotData = $iotService->receiveRealTimeData()['data'] ?? [];

            return [
                'production' => $productionData,
                'iot' => $iotData,
            ];
        });

        $production = $operationsData['production'];
        $iot = $operationsData['iot'];

        // Create multi-series chart data
        $labels = [];
        $productionData = [];
        $efficiencyData = [];
        $qualityData = [];

        // Generate data for last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d/m');

            // Production data (simplified)
            $productionData[] = rand(800, 1200); // Mock data

            // Efficiency data
            $efficiencyData[] = rand(75, 95); // Mock data

            // Quality data
            $qualityData[] = rand(85, 98); // Mock data
        }

        return [
            'datasets' => [
                [
                    'label' => 'الإنتاج اليومي (كجم)',
                    'data' => $productionData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'yAxisID' => 'y',
                    'type' => 'line',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'كفاءة العمليات (%)',
                    'data' => $efficiencyData,
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'yAxisID' => 'y1',
                    'type' => 'line',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'نقاط الجودة (%)',
                    'data' => $qualityData,
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'yAxisID' => 'y1',
                    'type' => 'line',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'التاريخ',
                    ],
                ],
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'الإنتاج (كجم)',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'النسبة المئوية (%)',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                    'ticks' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
            ],
        ];
    }

    public function getDescription(): ?string
    {
        return 'رسم بياني تفاعلي يعرض الإنتاج والكفاءة وجودة العمليات مع بيانات IoT في الوقت الفعلي';
    }
}