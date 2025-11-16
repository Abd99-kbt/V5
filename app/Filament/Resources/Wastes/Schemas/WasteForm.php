<?php

namespace App\Filament\Resources\Wastes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use App\Models\Product;

class WasteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label(__('wastes.product'))
                    ->options(Product::all()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                TextInput::make('quantity')
                    ->label(__('wastes.quantity'))
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->minValue(1),
                TextInput::make('reason')
                    ->label(__('wastes.reason'))
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label(__('wastes.notes'))
                    ->rows(4)
                    ->columnSpanFull(),
                Toggle::make('is_resolved')
                    ->label(__('wastes.is_resolved'))
                    ->default(false),
            ]);
    }
}