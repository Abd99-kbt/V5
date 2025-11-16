<?php

namespace App\Filament\Resources\WorkStages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WorkStageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name_en')
                    ->required()
                    ->label(__('work_stages.name_en'))
                    ->maxLength(255),
                TextInput::make('name_ar')
                    ->required()
                    ->label(__('work_stages.name_ar'))
                    ->maxLength(255),
                Textarea::make('description_en')
                    ->label(__('work_stages.description_en'))
                    ->rows(4)
                    ->columnSpanFull(),
                Textarea::make('description_ar')
                    ->label(__('work_stages.description_ar'))
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('order')
                    ->required()
                    ->label(__('work_stages.order'))
                    ->numeric()
                    ->default(1)
                    ->minValue(1),
                Toggle::make('is_active')
                    ->label(__('work_stages.is_active'))
                    ->default(true),
            ]);
    }
}