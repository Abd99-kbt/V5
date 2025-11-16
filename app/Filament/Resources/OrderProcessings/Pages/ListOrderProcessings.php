<?php

namespace App\Filament\Resources\OrderProcessings\Pages;

use App\Filament\Resources\OrderProcessings\OrderProcessingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrderProcessings extends ListRecords
{
    protected static string $resource = OrderProcessingResource::class;

    public function getTitle(): string
    {
        return 'معالجة الطلبات';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}