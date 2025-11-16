<?php

namespace App\Filament\Resources\OrderProcessings\Schemas;

use App\Models\Order;
use App\Models\WorkStage;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Grid;

class OrderProcessingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'order_number')
                    ->required()
                    ->label(__('order_processings.order_id')),
                Select::make('work_stage_id')
                    ->relationship('workStage', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn (WorkStage $record): string => $record->name)
                    ->required()
                    ->label(__('order_processings.work_stage_id'))
                    ->reactive(),
                Select::make('status')
                    ->options([
                        'pending' => __('order_processings.status_options.pending'),
                        'in_progress' => __('order_processings.status_options.in_progress'),
                        'completed' => __('order_processings.status_options.completed'),
                        'cancelled' => __('order_processings.status_options.cancelled'),
                    ])
                    ->default('pending')
                    ->required()
                    ->label(__('order_processings.status')),
                DateTimePicker::make('started_at')
                    ->label(__('order_processings.started_at')),
                DateTimePicker::make('completed_at')
                    ->label(__('order_processings.completed_at')),
                Textarea::make('notes')
                    ->columnSpanFull()
                    ->label(__('order_processings.notes')),
                Select::make('assigned_to')
                    ->relationship('assignedUser', 'name')
                    ->label(__('order_processings.assigned_to')),

                // Warehouse-specific fields (only show for Material Reservation stage)
                TextInput::make('actual_weight_received')
                    ->numeric()
                    ->step(0.01)
                    ->required(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Material Reservation')
                    ->label(__('order_processings.actual_weight_received'))
                    ->helperText(__('order_processings.weight_received_help'))
                    ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Material Reservation'),
                TextInput::make('weight_to_transfer')
                    ->numeric()
                    ->step(0.01)
                    ->label(__('order_processings.weight_to_transfer'))
                    ->helperText(__('order_processings.weight_transferred_help'))
                    ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Material Reservation'),
                Select::make('transfer_destination')
                    ->options(__('order_processings.transfer_destination_options'))
                    ->label(__('order_processings.transfer_destination'))
                    ->helperText(__('order_processings.transfer_destination_help'))
                    ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Material Reservation' && $get('weight_to_transfer') > 0),
                TextInput::make('weight_balance')
                    ->numeric()
                    ->step(0.01)
                    ->disabled()
                    ->dehydrated(false)
                    ->label(__('order_processings.weight_balance'))
                    ->helperText(__('order_processings.weight_balance_help'))
                    ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Material Reservation'),
                Toggle::make('transfer_approved')
                    ->label(__('order_processings.transfer_approved'))
                    ->helperText(__('order_processings.transfer_approved_help'))
                    ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Material Reservation'),
                DateTimePicker::make('transfer_approved_at')
                    ->label(__('order_processings.transfer_approved_at'))
                    ->disabled()
                    ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Material Reservation'),
                Select::make('transfer_approved_by')
                    ->relationship('transferApprover', 'name')
                    ->label(__('order_processings.transfer_approved_by'))
                    ->disabled()
                    ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Material Reservation'),
                Textarea::make('transfer_notes')
                    ->columnSpanFull()
                    ->label(__('order_processings.transfer_notes'))
                    ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Material Reservation'),

                TextInput::make('priority')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->label(__('order_processings.priority')),
                Toggle::make('mandatory_handover')
                    ->label(__('order_processings.mandatory_handover'))
                    ->helperText(__('order_processings.mandatory_handover_help'))
                    ->reactive(),
                Select::make('handover_status')
                    ->options([
                        'not_required' => __('order_processings.handover_status_options.not_required'),
                        'pending' => __('order_processings.handover_status_options.pending'),
                        'in_progress' => __('order_processings.handover_status_options.in_progress'),
                        'completed' => __('order_processings.handover_status_options.completed'),
                    ])
                    ->default('not_required')
                    ->required()
                    ->visible(fn ($get) => $get('mandatory_handover'))
                    ->label(__('order_processings.handover_status')),
                Select::make('handover_from')
                    ->relationship('handoverFromUser', 'name')
                    ->visible(fn ($get) => $get('mandatory_handover'))
                    ->label(__('order_processings.handover_from')),
                Select::make('handover_to')
                    ->relationship('handoverToUser', 'name')
                    ->visible(fn ($get) => $get('mandatory_handover'))
                    ->label(__('order_processings.handover_to')),
                DateTimePicker::make('handover_requested_at')
                    ->visible(fn ($get) => $get('mandatory_handover'))
                    ->label(__('order_processings.handover_requested_at')),
                DateTimePicker::make('handover_completed_at')
                    ->visible(fn ($get) => $get('mandatory_handover'))
                    ->label(__('order_processings.handover_completed_at')),
                Textarea::make('handover_notes')
                    ->columnSpanFull()
                    ->visible(fn ($get) => $get('mandatory_handover'))
                    ->label(__('order_processings.handover_notes')),

                // Sorting-specific fields (only show for Sorting stage)
                Section::make('Sorting Information')
                    ->schema([
                        Toggle::make('sorting_approved')
                            ->label(__('order_processings.sorting_approved'))
                            ->helperText(__('order_processings.sorting_approved_help'))
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),
                        DateTimePicker::make('sorting_approved_at')
                            ->label(__('order_processings.sorting_approved_at'))
                            ->disabled()
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),
                        Select::make('sorting_approved_by')
                            ->relationship('sortingApprover', 'name')
                            ->label(__('order_processings.sorting_approved_by'))
                            ->disabled()
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),
                        Textarea::make('sorting_notes')
                            ->columnSpanFull()
                            ->label(__('order_processings.sorting_notes'))
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),
                    ])
                    ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting')
                    ->columns(2),

                Section::make('Sorting Results')
                    ->schema([
                        TextInput::make('roll1_weight')
                            ->numeric()
                            ->step(0.01)
                            ->label(__('order_processings.roll1_weight'))
                            ->helperText(__('order_processings.roll1_weight_help'))
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),
                        TextInput::make('roll1_width')
                            ->numeric()
                            ->step(0.01)
                            ->label(__('order_processings.roll1_width'))
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),
                        TextInput::make('roll1_location')
                            ->label(__('order_processings.roll1_location'))
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),

                        TextInput::make('roll2_weight')
                            ->numeric()
                            ->step(0.01)
                            ->label(__('order_processings.roll2_weight'))
                            ->helperText(__('order_processings.roll2_weight_help'))
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),
                        TextInput::make('roll2_width')
                            ->numeric()
                            ->step(0.01)
                            ->label(__('order_processings.roll2_width'))
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),
                        TextInput::make('roll2_location')
                            ->label(__('order_processings.roll2_location'))
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),

                        TextInput::make('sorting_waste_weight')
                            ->numeric()
                            ->step(0.01)
                            ->label(__('order_processings.sorting_waste_weight'))
                            ->helperText(__('order_processings.sorting_waste_help'))
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),
                    ])
                    ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting')
                    ->columns(3),

                Section::make('Post-Sorting Transfer')
                    ->schema([
                        Select::make('post_sorting_destination')
                            ->options([
                                'cutting_warehouse' => __('order_processings.destination_cutting'),
                                'direct_delivery' => __('order_processings.destination_delivery'),
                                'other_warehouse' => __('order_processings.destination_other'),
                            ])
                            ->label(__('order_processings.post_sorting_destination'))
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),
                        Select::make('destination_warehouse_id')
                            ->relationship('destinationWarehouse', 'name')
                            ->label(__('order_processings.destination_warehouse'))
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting' && $get('post_sorting_destination') === 'other_warehouse'),
                        Toggle::make('transfer_completed')
                            ->label(__('order_processings.transfer_completed'))
                            ->disabled()
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),
                        DateTimePicker::make('transfer_completed_at')
                            ->label(__('order_processings.transfer_completed_at'))
                            ->disabled()
                            ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting'),
                    ])
                    ->visible(fn ($get) => $get('work_stage_id') && WorkStage::find($get('work_stage_id'))?->name_en === 'Sorting')
                    ->columns(2),
            ]);
    }
}