<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Order;
use Carbon\Carbon;

class SalesChartWidget extends ChartWidget
{
    protected ?string $heading = 'Sales Over Time';

    protected ?string $pollingInterval = '10s'; // For real-time updates

    protected function getData(): array
    {
        // Fetch sales data for the last 30 days
        $data = Order::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = $data->pluck('date')->toArray();
        $values = $data->pluck('total')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => $values,
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line'; // Chart.js type
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    public function getDescription(): ?string
    {
        return 'Line chart showing sales trends over the last 30 days with real-time updates.';
    }

    // RTL and dark mode are handled by Filament's ChartWidget
}