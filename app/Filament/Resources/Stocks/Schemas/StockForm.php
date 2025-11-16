<?php

namespace App\Filament\Resources\Stocks\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->label(__('stocks.product'))
                    ->required(),
                Select::make('warehouse_id')
                    ->relationship('warehouse')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->label(__('stocks.warehouse'))
                    ->required(),
                TextInput::make('quantity')
                    ->label(__('stocks.quantity'))
                    ->required()
                    ->numeric(),
                TextInput::make('reserved_quantity')
                    ->label(__('stocks.reserved_quantity'))
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('available_quantity')
                    ->label(__('stocks.available_quantity'))
                    ->numeric()
                    ->default(null),
                TextInput::make('unit_cost')
                    ->label(__('stocks.unit_cost'))
                    ->required()
                    ->numeric(),
                DatePicker::make('expiry_date')
                    ->label(__('stocks.expiry_date')),
                TextInput::make('batch_number')
                    ->label(__('stocks.batch_number'))
                    ->default(null),
                TextInput::make('location')
                    ->label(__('stocks.location'))
                    ->default(null),
                Toggle::make('is_active')
                    ->label(__('stocks.is_active'))
                    ->required(),
            ]);
    }
}
