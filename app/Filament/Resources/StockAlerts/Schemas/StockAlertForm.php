<?php

namespace App\Filament\Resources\StockAlerts\Schemas;

use App\Models\Product;
use App\Models\Warehouse;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StockAlertForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->name)
                    ->label(__('stock_alerts.product'))
                    ->required(),
                Select::make('warehouse_id')
                    ->relationship('warehouse', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn (Warehouse $record): string => $record->name)
                    ->label(__('stock_alerts.warehouse'))
                    ->required(),
                Select::make('type')
                    ->label(__('stock_alerts.type'))
                    ->options(__('stock_alerts.type_options'))
                    ->required(),
                Select::make('severity')
                    ->label(__('stock_alerts.severity'))
                    ->options(__('stock_alerts.severity_options'))
                    ->required(),
                TextInput::make('current_quantity')
                    ->label(__('stock_alerts.current_quantity'))
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('threshold_quantity')
                    ->label(__('stock_alerts.threshold_quantity'))
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('message')
                    ->label(__('stock_alerts.message'))
                    ->columnSpanFull(),
                Toggle::make('is_read')
                    ->label(__('stock_alerts.is_read')),
                Toggle::make('is_resolved')
                    ->label(__('stock_alerts.is_resolved')),
                DateTimePicker::make('resolved_at')
                    ->label(__('stock_alerts.resolved_at')),
            ]);
    }
}