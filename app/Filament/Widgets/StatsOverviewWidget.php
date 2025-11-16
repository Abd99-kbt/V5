<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use App\Events\StatsUpdated;
use Illuminate\Support\Facades\Http;
use Filament\Support\Enums\FontWeight;

class StatsOverviewWidget extends BaseWidget
{
    protected ?string $pollingInterval = '10s'; // For real-time updates

    public $listeners = ['echo:stats,StatsUpdated' => 'updateStats', 'refreshDashboard' => 'updateStats'];

    public $totalOrders;
    public $totalProducts;
    public $totalCustomers;
    public $totalSales;
    public $chartData;
    public $automationEfficiency;
    public $qualityScore;
    public $activeAlerts;
    public $iotDevicesOnline;

    public function mount()
    {
        $this->updateStats();
    }

    public function updateStats($event = null)
    {
        // If event is provided, use it; otherwise, fetch fresh data
        if ($event) {
            $stats = $event['stats'];
            $this->totalOrders = $stats['totalOrders'];
            $this->totalProducts = $stats['totalProducts'];
            $this->totalCustomers = $stats['totalCustomers'];
            $this->totalSales = $stats['totalSales'];
            $this->chartData = $stats['chartData'];
        } else {
            $this->fetchStats();
        }
    }

    private function fetchStats()
    {
        $this->totalOrders = Order::count();
        $this->totalProducts = Product::count();
        $this->totalCustomers = Customer::count();
        $this->totalSales = Order::sum('total_amount');

        $salesData = Order::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $this->chartData = [
            'labels' => array_keys($salesData),
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => array_values($salesData),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                ],
            ],
        ];

        // Fetch automation KPIs
        $this->fetchAutomationKPIs();
    }

    private function fetchAutomationKPIs()
    {
        // Automation Efficiency - based on automated processes vs manual
        $automatedOrders = Order::where('is_automated', true)->count();
        $totalOrders = $this->totalOrders;
        $this->automationEfficiency = $totalOrders > 0 ? round(($automatedOrders / $totalOrders) * 100, 1) : 0;

        // Quality Score - average from quality control checks
        $avgQualityScore = \App\Models\OrderProcessing::whereNotNull('quality_score')
            ->avg('quality_score');
        $this->qualityScore = $avgQualityScore ? round($avgQualityScore, 1) : 85.0; // Default if no data

        // Active Alerts - from stock alerts and system issues
        $this->activeAlerts = \App\Models\StockAlert::where('is_resolved', false)->count();

        // IoT Devices Online - from IoT service
        $iotService = app(\App\Services\IoTIntegrationService::class);
        $connectionStatus = $iotService->getConnectionStatus();
        $this->iotDevicesOnline = $connectionStatus['total_connected_devices'] ?? 0;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Total Orders', $this->totalOrders ?? 0)
                ->description('Total number of orders')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary')
                ->chart($this->chartData ?? []),

            Stat::make('Total Products', $this->totalProducts ?? 0)
                ->description('Total number of products')
                ->descriptionIcon('heroicon-m-cube')
                ->color('success'),

            Stat::make('Total Customers', $this->totalCustomers ?? 0)
                ->description('Total number of customers')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),

            Stat::make('Total Sales', '$' . number_format($this->totalSales ?? 0, 2))
                ->description('Total sales amount')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('danger')
                ->chart($this->chartData ?? []),

            // Automation KPIs
            Stat::make('Automation Efficiency', $this->automationEfficiency ?? 0 . '%')
                ->description('Overall automation performance')
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('info'),

            Stat::make('Quality Score', $this->qualityScore ?? 0 . '%')
                ->description('Average quality control score')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Active Alerts', $this->activeAlerts ?? 0)
                ->description('System alerts requiring attention')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($this->activeAlerts > 0 ? 'danger' : 'success'),

            Stat::make('IoT Devices Online', $this->iotDevicesOnline ?? 0)
                ->description('Connected IoT devices')
                ->descriptionIcon('heroicon-m-wifi')
                ->color('gray'),
        ];
    }

    public function getDescription(): ?string
    {
        return 'Overview of key metrics with real-time updates via Laravel Echo.';
    }

    // For RTL and dark mode compatibility, Filament handles this automatically
}