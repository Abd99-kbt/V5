<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use App\Models\Customer;
use App\Models\Product;
use App\Services\AutomatedInvoiceNumberService;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label(__('invoices.customer'))
                    ->options(Customer::all()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                TextInput::make('invoice_number')
                    ->label(__('invoices.invoice_number'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->default(fn () => app(AutomatedInvoiceNumberService::class)->generateInvoiceNumber())
                    ->disabled()
                    ->helperText(__('invoices.invoice_number_auto_generated')),
                DatePicker::make('invoice_date')
                    ->label(__('invoices.invoice_date'))
                    ->required()
                    ->default(now()),
                DatePicker::make('due_date')
                    ->label(__('invoices.due_date'))
                    ->required(),
                Repeater::make('invoice_items')
                    ->label(__('invoices.items'))
                    ->schema([
                        Select::make('product_id')
                            ->label(__('invoices.product'))
                            ->options(Product::all()->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $product = Product::find($state);
                                if ($product) {
                                    $set('unit_price', $product->selling_price ?? 0);
                                    $quantity = $get('quantity') ?? 1;
                                    $set('total_price', ($product->selling_price ?? 0) * $quantity);
                                }
                            }),
                        TextInput::make('quantity')
                            ->label(__('invoices.quantity'))
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $unitPrice = $get('unit_price') ?? 0;
                                $set('total_price', $unitPrice * ($state ?? 1));
                            }),
                        TextInput::make('unit_price')
                            ->label(__('invoices.unit_price'))
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $quantity = $get('quantity') ?? 1;
                                $set('total_price', ($state ?? 0) * $quantity);
                            }),
                        TextInput::make('total_price')
                            ->label(__('invoices.total_price'))
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->disabled()
                            ->reactive(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => Product::find($state['product_id'] ?? null)?->name ?? null)
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        self::updateTotals($set, $get);
                    })
                    ->defaultItems(1),
                TextInput::make('subtotal')
                    ->label(__('invoices.subtotal'))
                    ->numeric()
                    ->default(0)
                    ->prefix('$')
                    ->disabled()
                    ->reactive(),
                TextInput::make('tax_amount')
                    ->label(__('invoices.tax_amount'))
                    ->numeric()
                    ->default(0)
                    ->prefix('$')
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        self::updateTotals($set, $get);
                    }),
                TextInput::make('discount_amount')
                    ->label(__('invoices.discount_amount'))
                    ->numeric()
                    ->default(0)
                    ->prefix('$')
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        self::updateTotals($set, $get);
                    }),
                TextInput::make('total_amount')
                    ->label(__('invoices.total_amount'))
                    ->numeric()
                    ->default(0)
                    ->prefix('$')
                    ->disabled()
                    ->reactive(),
                Toggle::make('is_paid')
                    ->label(__('invoices.is_paid'))
                    ->default(false),
                DatePicker::make('paid_at')
                    ->label(__('invoices.paid_at'))
                    ->visible(fn ($get) => $get('is_paid')),
            ]);
    }

    protected static function updateTotals(callable $set, callable $get): void
    {
        $items = $get('invoice_items') ?? [];
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += $item['total_price'] ?? 0;
        }

        $set('subtotal', $subtotal);

        $taxAmount = $get('tax_amount') ?? 0;
        $discountAmount = $get('discount_amount') ?? 0;

        $totalAmount = $subtotal + $taxAmount - $discountAmount;
        $set('total_amount', max(0, $totalAmount));
    }
}