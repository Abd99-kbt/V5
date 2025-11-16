<?php

namespace App\Filament\Resources\Wastes;

use App\Filament\Resources\Wastes\Pages\CreateWaste;
use App\Filament\Resources\Wastes\Pages\EditWaste;
use App\Filament\Resources\Wastes\Pages\ListWastes;
use App\Filament\Resources\Wastes\Schemas\WasteForm;
use App\Filament\Resources\Wastes\Tables\WastesTable;
use App\Models\Waste;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WasteResource extends Resource
{
    protected static ?string $model = Waste::class;

    protected static ?string $label = 'النفايات';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return 'النفايات';
    }

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return WasteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WastesTable::configure($table);
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
            'index' => ListWastes::route('/'),
            'create' => CreateWaste::route('/create'),
            'edit' => EditWaste::route('/{record}/edit'),
        ];
    }
}