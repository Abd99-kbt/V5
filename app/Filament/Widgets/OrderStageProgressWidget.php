<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\WorkStage;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrderStageProgressWidget extends ChartWidget
{
    protected ?string $heading = 'توزيع مراحل الطلبات';

    protected function getData(): array
    {
        $stageData = Order::select('current_stage', DB::raw('count(*) as count'))
                         ->groupBy('current_stage')
                         ->get()
                         ->pluck('count', 'current_stage')
                         ->toArray();

        $workStages = WorkStage::active()->orderBy('order')->get();

        $labels = [];
        $data = [];
        $backgroundColors = [];

        $stageColors = [
            'إنشاء' => '#6B7280',
            'مراجعة' => '#F59E0B',
            'حجز_المواد' => '#3B82F6',
            'فرز' => '#8B5CF6',
            'قص' => '#F97316',
            'تعبئة' => '#6366F1',
            'فوترة' => '#10B981',
            'تسليم' => '#059669',
        ];

        foreach ($workStages as $stage) {
            $stageName = $stage->name_ar;
            $labels[] = $stageName;
            $data[] = $stageData[$stageName] ?? 0;
            $backgroundColors[] = $stageColors[$stageName] ?? '#6B7280';
        }

        return [
            'datasets' => [
                [
                    'label' => 'عدد الطلبات',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $backgroundColors,
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }
}
