<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Supplier;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name_en')
                    ->required()
                    ->label('الاسم (الإنجليزية)'),
                TextInput::make('name_ar')
                    ->required()
                    ->label('الاسم (العربية)'),
                TextInput::make('sku')
                    ->label('رمز المنتج')
                    ->required(),
                TextInput::make('barcode')
                    ->label('الباركود')
                    ->default(null),
                TextInput::make('description_en')
                    ->label('الوصف (الإنجليزية)')
                    ->default(null),
                Textarea::make('description_ar')
                    ->label('الوصف (العربية)')
                    ->default(null)
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->image()
                    ->label('الصورة'),
                Select::make('type')
                    ->options([
                        'roll' => 'لفة',
                        'digma' => 'ديجما',
                        'bale' => 'بالة',
                        'sheet' => 'شريحة',
                    ])
                    ->default('roll')
                    ->required()
                    ->label('النوع'),
                TextInput::make('grammage')
                    ->numeric()
                    ->label('الجراماج')
                    ->default(null),
                TextInput::make('quality')
                    ->label('الجودة')
                    ->default(null),
                TextInput::make('roll_number')
                    ->label('رقم اللفة')
                    ->default(null),
                TextInput::make('source')
                    ->label('المصدر')
                    ->default(null),
                TextInput::make('length')
                    ->numeric()
                    ->label('الطول')
                    ->default(null),
                TextInput::make('width')
                    ->numeric()
                    ->label('العرض')
                    ->default(null),
                TextInput::make('thickness')
                    ->numeric()
                    ->label('السماكة')
                    ->default(null),
                TextInput::make('purchase_price')
                    ->required()
                    ->numeric()
                    ->label('سعر الشراء'),
                TextInput::make('selling_price')
                    ->required()
                    ->numeric()
                    ->label('سعر البيع'),
                TextInput::make('wholesale_price')
                    ->numeric()
                    ->label('سعر الجملة')
                    ->default(null),
                TextInput::make('material_cost_per_ton')
                    ->numeric()
                    ->label('تكلفة المواد للطن')
                    ->default(null),
                TextInput::make('min_stock_level')
                    ->required()
                    ->numeric()
                    ->label('الحد الأدنى للمخزون')
                    ->default(0),
                TextInput::make('max_stock_level')
                    ->numeric()
                    ->label('الحد الأقصى للمخزون')
                    ->default(null),
                TextInput::make('unit')
                    ->required()
                    ->label('الوحدة')
                    ->default('kg'),
                TextInput::make('weight')
                    ->numeric()
                    ->label('الوزن')
                    ->default(null),
                TextInput::make('reserved_weight')
                    ->required()
                    ->numeric()
                    ->label('الوزن المحجوز')
                    ->default(0.0),
                Toggle::make('is_active')
                    ->required()
                    ->label('نشط'),
                Toggle::make('track_inventory')
                    ->required()
                    ->label('تتبع المخزون'),
                Select::make('category_id')
                    ->relationship('category', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn (Category $record): string => $record->name)
                    ->required()
                    ->label('الفئة'),
                Select::make('supplier_id')
                    ->relationship('supplier', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn (Supplier $record): string => $record->name)
                    ->default(null)
                    ->label('المورد'),
            ]);
    }
}
