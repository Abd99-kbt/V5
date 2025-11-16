<?php

namespace App\Filament\Resources\OrderItems\Schemas;

use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'order_number')
                    ->label(__('order_items.order'))
                    ->required(),
                Select::make('product_id')
                    ->relationship('product', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->name)
                    ->label(__('order_items.product'))
                    ->required(),
                TextInput::make('quantity')
                    ->label(__('order_items.quantity'))
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('unit_price')
                    ->label(__('order_items.unit_price'))
                    ->required()
                    ->numeric()
                    ->default(0.00),
                TextInput::make('total_price')
                    ->label(__('order_items.total_price'))
                    ->required()
                    ->numeric()
                    ->default(0.00),
                TextInput::make('discount')
                    ->label(__('order_items.discount'))
                    ->numeric()
                    ->default(0.00),
                Textarea::make('notes')
                    ->label(__('order_items.notes'))
                    ->columnSpanFull(),
            ]);
    }
}