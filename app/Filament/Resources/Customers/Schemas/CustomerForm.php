<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerForm
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
                TextInput::make('province_en')
                    ->label('المحافظة (الإنجليزية)'),
                TextInput::make('province_ar')
                    ->label('المحافظة (العربية)'),
                TextInput::make('mobile_number')
                    ->tel()
                    ->label('رقم الهاتف'),
                TextInput::make('follow_up_person_en')
                    ->label('شخص المتابعة (الإنجليزية)'),
                TextInput::make('follow_up_person_ar')
                    ->label('شخص المتابعة (العربية)'),
                Textarea::make('address_en')
                    ->label('العنوان (الإنجليزية)')
                    ->columnSpanFull(),
                Textarea::make('address_ar')
                    ->label('العنوان (العربية)')
                    ->columnSpanFull(),
                TextInput::make('email')
                    ->email()
                    ->label('البريد الإلكتروني'),
                TextInput::make('tax_number')
                    ->label('الرقم الضريبي'),
                TextInput::make('credit_limit')
                    ->numeric()
                    ->default(0.00)
                    ->minValue(0)
                    ->maxValue(99999999.99)
                    ->label('حد الائتمان'),
                Select::make('customer_type')
                    ->options(['individual' => 'فرد', 'company' => 'شركة'])
                    ->required()
                    ->label('نوع العميل'),
                Toggle::make('is_active')
                    ->required()
                    ->label('نشط'),
            ]);
    }
}