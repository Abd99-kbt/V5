<?php

namespace App\Filament\Resources\WorkStages;

use App\Filament\Resources\WorkStages\Pages\CreateWorkStage;
use App\Filament\Resources\WorkStages\Pages\EditWorkStage;
use App\Filament\Resources\WorkStages\Pages\ListWorkStages;
use App\Filament\Resources\WorkStages\Schemas\WorkStageForm;
use App\Filament\Resources\WorkStages\Tables\WorkStagesTable;
use App\Models\WorkStage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkStageResource extends Resource
{
    protected static ?string $model = WorkStage::class;

    protected static ?string $label = 'مراحل العمل';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return 'مراحل العمل';
    }

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return WorkStageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkStagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkStages::route('/'),
            'create' => CreateWorkStage::route('/create'),
            'edit' => EditWorkStage::route('/{record}/edit'),
        ];
    }
}