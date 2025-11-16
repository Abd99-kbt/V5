<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WarehouseForm
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
                TextInput::make('code')
                    ->required()
                    ->label('الرمز'),
                Textarea::make('address_en')
                    ->required()
                    ->columnSpanFull()
                    ->label('العنوان (الإنجليزية)'),
                Textarea::make('address_ar')
                    ->required()
                    ->columnSpanFull()
                    ->label('العنوان (العربية)'),
                TextInput::make('phone')
                    ->tel()
                    ->default(null)
                    ->label('الهاتف'),
                TextInput::make('manager_name')
                    ->default(null)
                    ->label('اسم المدير'),
                Select::make('type')
                    ->options([
                        'main' => 'رئيسي',
                        'scrap' => 'خردة',
                        'sorting' => 'فرز',
                        'custody' => 'حضانة',
                    ])
                    ->default('main')
                    ->required()
                    ->label('النوع'),
                TextInput::make('total_capacity')
                    ->required()
                    ->default(0.0)
                    ->label('إجمالي السعة'),
                TextInput::make('used_capacity')
                    ->required()
                    ->default(0.0)
                    ->label('السعة المستخدمة'),
                TextInput::make('reserved_capacity')
                    ->required()
                    ->default(0.0)
                    ->label('السعة المحجوزة'),
                Toggle::make('is_active')
                    ->required()
                    ->label('نشط'),
                Toggle::make('is_main')
                    ->required()
                    ->label('رئيسي'),
                Toggle::make('accepts_transfers')
                    ->required()
                    ->label('يقبل التحويلات'),
                Toggle::make('requires_approval')
                    ->required()
                    ->label('يتطلب موافقة'),
            ]);
    }
}
