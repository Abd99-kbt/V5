<?php

namespace App\Filament\Resources\WorkStages\Pages;

use App\Filament\Resources\WorkStages\WorkStageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkStage extends EditRecord
{
    protected static string $resource = WorkStageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}