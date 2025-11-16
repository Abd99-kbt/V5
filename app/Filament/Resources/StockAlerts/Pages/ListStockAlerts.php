<?php

namespace App\Filament\Resources\StockAlerts\Pages;

use App\Filament\Resources\StockAlerts\StockAlertResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockAlerts extends ListRecords
{
    protected static string $resource = StockAlertResource::class;

    public function getTitle(): string
    {
        return 'تنبيهات المخزون';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}