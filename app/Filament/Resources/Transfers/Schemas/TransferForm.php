<?php

namespace App\Filament\Resources\Transfers\Schemas;

use App\Models\Product;
use App\Models\Warehouse;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('source_warehouse_id')
                    ->label(__('transfers.source_warehouse'))
                    ->options(Warehouse::pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Select::make('destination_warehouse_id')
                    ->label(__('transfers.destination_warehouse'))
                    ->options(Warehouse::pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->different('source_warehouse_id'),

                Select::make('product_id')
                    ->label(__('transfers.product'))
                    ->options(Product::all()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                TextInput::make('quantity')
                    ->label(__('transfers.quantity'))
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->step(0.01),

                Select::make('unit')
                    ->label(__('transfers.unit'))
                    ->options([
                        'kg' => 'كيلوغرام',
                        'piece' => 'قطعة',
                        'meter' => 'متر',
                        'liter' => 'لتر',
                    ])
                    ->default('kg')
                    ->required(),

                Textarea::make('reason')
                    ->label(__('transfers.reason'))
                    ->rows(3)
                    ->nullable(),

                TextInput::make('requested_by')
                    ->default(\Illuminate\Support\Facades\Auth::id())
                    ->disabled()
                    ->hidden(),

                TextInput::make('id')
                    ->disabled()
                    ->hidden(),

                TextInput::make('created_at')
                    ->disabled()
                    ->hidden(),

                TextInput::make('updated_at')
                    ->disabled()
                    ->hidden(),
            ]);
    }
}