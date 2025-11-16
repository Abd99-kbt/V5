<?php

namespace App\Filament\Resources\WorkStages\Pages;

use App\Filament\Resources\WorkStages\WorkStageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkStages extends ListRecords
{
    protected static string $resource = WorkStageResource::class;

    public function getTitle(): string
    {
        return 'مراحل العمل';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}