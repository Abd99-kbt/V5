<?php

namespace App\Filament\Resources\Wastes\Pages;

use App\Filament\Resources\Wastes\WasteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWastes extends ListRecords
{
    protected static string $resource = WasteResource::class;

    public function getTitle(): string
    {
        return 'النفايات';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}