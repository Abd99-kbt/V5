<?php

namespace App\Filament\Resources\Approvals\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use App\Models\User;

class ApprovalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label(__('approvals.type'))
                    ->options([
                        'general' => __('approvals.type_general'),
                        'order' => __('approvals.type_order'),
                        'product' => __('approvals.type_product'),
                        'customer' => __('approvals.type_customer'),
                    ])
                    ->required()
                    ->default('general'),
                Select::make('status')
                    ->label(__('approvals.status'))
                    ->options([
                        'pending' => __('approvals.status_pending'),
                        'approved' => __('approvals.status_approved'),
                        'rejected' => __('approvals.status_rejected'),
                    ])
                    ->required()
                    ->default('pending'),
                Textarea::make('notes')
                    ->label(__('approvals.notes'))
                    ->rows(4)
                    ->columnSpanFull(),
                Select::make('user_id')
                    ->label(__('approvals.user'))
                    ->options(User::pluck('name', 'id'))
                    ->searchable(),
            ]);
    }
}