<?php

namespace App\Filament\Resources\OrderProcessings;

use App\Filament\Resources\OrderProcessings\Pages\CreateOrderProcessing;
use App\Filament\Resources\OrderProcessings\Pages\EditOrderProcessing;
use App\Filament\Resources\OrderProcessings\Pages\ListOrderProcessings;
use App\Filament\Resources\OrderProcessings\Schemas\OrderProcessingForm;
use App\Filament\Resources\OrderProcessings\Tables\OrderProcessingsTable;
use App\Models\OrderProcessing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class OrderProcessingResource extends Resource
{
    protected static ?string $model = OrderProcessing::class;

    protected static ?string $label = 'معالجة الطلبات';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return 'معالجة الطلبات';
    }

    protected static ?string $recordTitleAttribute = 'order.order_number';

    public static function form(Schema $schema): Schema
    {
        return OrderProcessingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderProcessingsTable::configure($table);
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
            'index' => ListOrderProcessings::route('/'),
            'create' => CreateOrderProcessing::route('/create'),
            'edit' => EditOrderProcessing::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Auth::user()->can('view order_processings') || Auth::user()->can('manage order_processings');
    }

    public static function canCreate(): bool
    {
        return Auth::user()->can('create order_processings') || Auth::user()->can('manage order_processings');
    }

    public static function canEdit($record): bool
    {
        return Auth::user()->can('edit order_processings') || Auth::user()->can('manage order_processings');
    }

    public static function canDelete($record): bool
    {
        return Auth::user()->can('delete order_processings') || Auth::user()->can('manage order_processings');
    }
}