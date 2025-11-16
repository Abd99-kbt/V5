<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('رمز المنتج')
                    ->searchable(),
                TextColumn::make('barcode')
                    ->label('الباركود')
                    ->searchable(),
                ImageColumn::make('image')
                    ->label('الصورة'),
                TextColumn::make('type_label')
                    ->label('النوع')
                    ->badge(),
                TextColumn::make('quality_label')
                    ->label('الجودة')
                    ->searchable(),
                TextColumn::make('category.name_en')
                    ->label('الفئة')
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->category?->name ?? 'N/A'),
                TextColumn::make('supplier.name_en')
                    ->label('المورد')
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->supplier?->name ?? 'N/A'),
                TextColumn::make('total_stock')
                    ->label('إجمالي المخزون')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('available_stock')
                    ->label('المخزون المتاح')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('profit_margin')
                    ->label('هامش الربح %')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('selling_price')
                    ->label('سعر البيع')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('purchase_price')
                    ->label('سعر الشراء')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_stock_level')
                    ->label('الحد الأدنى للمخزون')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_stock_level')
                    ->label('الحد الأقصى للمخزون')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
                IconColumn::make('track_inventory')
                    ->label('تتبع المخزون')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('products.created_at')),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('products.updated_at')),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->relationship('category', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->label('الفئة'),
                SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->label('المورد'),
                SelectFilter::make('type')
                    ->options([
                        'roll' => 'لفة',
                        'digma' => 'ديجما',
                        'bale' => 'بالة',
                        'sheet' => 'شريحة',
                    ])
                    ->label('النوع'),
                SelectFilter::make('quality')
                    ->options([
                        'standard' => 'قياسي',
                        'stock' => 'مخزون',
                        'premium' => 'ممتاز',
                    ])
                    ->label('الجودة'),
                SelectFilter::make('is_active')
                    ->options([
                        true => 'نشط',
                        false => 'غير نشط',
                    ])
                    ->label('نشط'),
                SelectFilter::make('track_inventory')
                    ->options([
                        true => 'متبع',
                        false => 'غير متبع',
                    ])
                    ->label('تتبع المخزون'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
