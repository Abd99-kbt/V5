<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupplierForm
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
                TextInput::make('contact_person_en')
                    ->label('شخص الاتصال (الإنجليزية)'),
                TextInput::make('contact_person_ar')
                    ->label('شخص الاتصال (العربية)'),
                TextInput::make('email')
                    ->email()
                    ->label('البريد الإلكتروني'),
                TextInput::make('phone')
                    ->tel()
                    ->label('الهاتف'),
                Textarea::make('address_en')
                    ->label('العنوان (الإنجليزية)')
                    ->columnSpanFull(),
                Textarea::make('address_ar')
                    ->label('العنوان (العربية)')
                    ->columnSpanFull(),
                TextInput::make('tax_number')
                    ->label('الرقم الضريبي'),
                TextInput::make('commercial_register')
                    ->label('السجل التجاري'),
                TextInput::make('credit_limit')
                    ->default(0.00)
                    ->label('حد الائتمان'),
                TextInput::make('payment_terms')
                    ->default(0)
                    ->label('شروط الدفع'),
                Toggle::make('is_active')
                    ->required()
                    ->label('نشط'),
            ]);
    }
}