<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
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
                Textarea::make('description_en')
                    ->label('الوصف (الإنجليزية)')
                    ->columnSpanFull(),
                Textarea::make('description_ar')
                    ->label('الوصف (العربية)')
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->image()
                    ->directory('categories')
                    ->label('الصورة'),
                Toggle::make('is_active')
                    ->required()
                    ->label('نشط'),
            ]);
    }
}