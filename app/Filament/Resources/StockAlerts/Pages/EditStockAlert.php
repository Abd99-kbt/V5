<?php

namespace App\Filament\Resources\StockAlerts\Pages;

use App\Filament\Resources\StockAlerts\StockAlertResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStockAlert extends EditRecord
{
    protected static string $resource = StockAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}