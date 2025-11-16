<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(function () {
                return \App\Models\Order::query()->select([
                    'id', 'order_number', 'customer_id', 'status', 'current_stage',
                    'total_amount', 'created_at', 'required_date', 'is_urgent'
                ])->with([
                    'customer:id,name_en,name_ar',
                    'assignedUser:id,name'
                ]);
            })
            ->columns([
                TextColumn::make('order_number')
                    ->label(__('orders.order_number'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label(__('orders.status'))
                    ->colors([
                        'success' => 'مكتمل',
                        'warning' => 'قيد_المراجعة',
                        'danger' => 'ملغي',
                        'primary' => 'مؤكد',
                        'secondary' => 'قيد_التنفيذ',
                        'gray' => 'مسودة',
                    ])
                    ->sortable(),

                BadgeColumn::make('current_stage')
                    ->label(__('orders.current_stage'))
                    ->colors([
                        'gray' => 'إنشاء',
                        'yellow' => 'مراجعة',
                        'blue' => 'حجز_المواد',
                        'purple' => 'فرز',
                        'orange' => 'قص',
                        'indigo' => 'تعبئة',
                        'green' => 'فوترة',
                        'emerald' => 'تسليم',
                    ])
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label(__('orders.total_amount'))
                    ->money('SAR')
                    ->sortable(),

                IconColumn::make('is_urgent')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger')
                    ->label('عاجل'),

                TextColumn::make('created_at')
                    ->label(__('orders.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('required_date')
                    ->label(__('orders.required_date'))
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->required_date < now() ? 'danger' : 'success'),
            ])
            ->filters([
                // Date range filters for timestamps
                Filter::make('submitted_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('submitted_from')
                            ->label(__('orders.submitted_from')),
                        \Filament\Forms\Components\DatePicker::make('submitted_to')
                            ->label(__('orders.submitted_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['submitted_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '>=', $date),
                            )
                            ->when(
                                $data['submitted_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '<=', $date),
                            );
                    }),

                Filter::make('approved_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('approved_from')
                            ->label(__('orders.approved_from')),
                        \Filament\Forms\Components\DatePicker::make('approved_to')
                            ->label(__('orders.approved_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['approved_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('approved_at', '>=', $date),
                            )
                            ->when(
                                $data['approved_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('approved_at', '<=', $date),
                            );
                    }),

                Filter::make('started_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('started_from')
                            ->label(__('orders.started_from')),
                        \Filament\Forms\Components\DatePicker::make('started_to')
                            ->label(__('orders.started_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['started_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('started_at', '>=', $date),
                            )
                            ->when(
                                $data['started_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('started_at', '<=', $date),
                            );
                    }),

                Filter::make('completed_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('completed_from')
                            ->label(__('orders.completed_from')),
                        \Filament\Forms\Components\DatePicker::make('completed_to')
                            ->label(__('orders.completed_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['completed_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('completed_at', '>=', $date),
                            )
                            ->when(
                                $data['completed_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('completed_at', '<=', $date),
                            );
                    }),

                // Select filters for categorical fields
                SelectFilter::make('type')
                    ->options([
                        'in' => __('orders.type_in'),
                        'out' => __('orders.type_out'),
                    ])
                    ->label(__('orders.type')),

                SelectFilter::make('status')
                    ->options([
                        'مسودة' => __('orders.status_draft'),
                        'قيد_المراجعة' => __('orders.status_review'),
                        'مؤكد' => __('orders.status_confirmed'),
                        'قيد_التنفيذ' => __('orders.status_processing'),
                        'مكتمل' => __('orders.status_completed'),
                        'ملغي' => __('orders.status_cancelled'),
                    ])
                    ->label(__('orders.status')),

                SelectFilter::make('current_stage')
                    ->options([
                        'إنشاء' => __('orders.stage_creation'),
                        'مراجعة' => __('orders.stage_review'),
                        'حجز_المواد' => __('orders.stage_material_reservation'),
                        'فرز' => __('orders.stage_sorting'),
                        'قص' => __('orders.stage_cutting'),
                        'تعبئة' => __('orders.stage_packaging'),
                        'فوترة' => __('orders.stage_invoicing'),
                        'تسليم' => __('orders.stage_delivery'),
                    ])
                    ->label(__('orders.current_stage')),

                SelectFilter::make('warehouse')
                    ->relationship('warehouse', 'name')
                    ->label(__('orders.warehouse')),

                SelectFilter::make('supplier')
                    ->relationship('supplier', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->label(__('orders.supplier')),

                SelectFilter::make('customer')
                    ->relationship('customer', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->label(__('orders.customer')),

                SelectFilter::make('material_type')
                    ->options([
                        'steel' => __('orders.material_steel'),
                        'aluminum' => __('orders.material_aluminum'),
                        'copper' => __('orders.material_copper'),
                        'brass' => __('orders.material_brass'),
                        'other' => __('orders.material_other'),
                    ])
                    ->label(__('orders.material_type')),

                SelectFilter::make('delivery_method')
                    ->options([
                        'pickup' => __('orders.delivery_pickup'),
                        'delivery' => __('orders.delivery_delivery'),
                        'shipping' => __('orders.delivery_shipping'),
                    ])
                    ->label(__('orders.delivery_method')),

                // Number range filters for financial elements
                Filter::make('total_amount')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('total_amount_from')
                            ->label(__('orders.total_amount_from'))
                            ->numeric()
                            ->prefix('$'),
                        \Filament\Forms\Components\TextInput::make('total_amount_to')
                            ->label(__('orders.total_amount_to'))
                            ->numeric()
                            ->prefix('$'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['total_amount_from'],
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '>=', $amount),
                            )
                            ->when(
                                $data['total_amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '<=', $amount),
                            );
                    }),

                Filter::make('paid_amount')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('paid_amount_from')
                            ->label(__('orders.paid_amount_from'))
                            ->numeric()
                            ->prefix('$'),
                        \Filament\Forms\Components\TextInput::make('paid_amount_to')
                            ->label(__('orders.paid_amount_to'))
                            ->numeric()
                            ->prefix('$'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['paid_amount_from'],
                                fn (Builder $query, $amount): Builder => $query->where('paid_amount', '>=', $amount),
                            )
                            ->when(
                                $data['paid_amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('paid_amount', '<=', $amount),
                            );
                    }),

                Filter::make('remaining_amount')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('remaining_amount_from')
                            ->label(__('orders.remaining_amount_from'))
                            ->numeric()
                            ->prefix('$'),
                        \Filament\Forms\Components\TextInput::make('remaining_amount_to')
                            ->label(__('orders.remaining_amount_to'))
                            ->numeric()
                            ->prefix('$'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['remaining_amount_from'],
                                fn (Builder $query, $amount): Builder => $query->where('remaining_amount', '>=', $amount),
                            )
                            ->when(
                                $data['remaining_amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('remaining_amount', '<=', $amount),
                            );
                    }),

                // Number range filters for material specifications
                Filter::make('required_weight')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('weight_from')
                            ->label(__('orders.weight_from'))
                            ->numeric()
                            ->suffix('kg'),
                        \Filament\Forms\Components\TextInput::make('weight_to')
                            ->label(__('orders.weight_to'))
                            ->numeric()
                            ->suffix('kg'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['weight_from'],
                                fn (Builder $query, $weight): Builder => $query->where('required_weight', '>=', $weight),
                            )
                            ->when(
                                $data['weight_to'],
                                fn (Builder $query, $weight): Builder => $query->where('required_weight', '<=', $weight),
                            );
                    }),

                Filter::make('required_length')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('length_from')
                            ->label(__('orders.length_from'))
                            ->numeric()
                            ->suffix('m'),
                        \Filament\Forms\Components\TextInput::make('length_to')
                            ->label(__('orders.length_to'))
                            ->numeric()
                            ->suffix('m'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['length_from'],
                                fn (Builder $query, $length): Builder => $query->where('required_length', '>=', $length),
                            )
                            ->when(
                                $data['length_to'],
                                fn (Builder $query, $length): Builder => $query->where('required_length', '<=', $length),
                            );
                    }),

                Filter::make('required_width')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('width_from')
                            ->label(__('orders.width_from'))
                            ->numeric()
                            ->suffix('m'),
                        \Filament\Forms\Components\TextInput::make('width_to')
                            ->label(__('orders.width_to'))
                            ->numeric()
                            ->suffix('m'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['width_from'],
                                fn (Builder $query, $width): Builder => $query->where('required_width', '>=', $width),
                            )
                            ->when(
                                $data['width_to'],
                                fn (Builder $query, $width): Builder => $query->where('required_width', '<=', $width),
                            );
                    }),

                Filter::make('required_plates')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('plates_from')
                            ->label(__('orders.plates_from'))
                            ->numeric(),
                        \Filament\Forms\Components\TextInput::make('plates_to')
                            ->label(__('orders.plates_to'))
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['plates_from'],
                                fn (Builder $query, $plates): Builder => $query->where('required_plates', '>=', $plates),
                            )
                            ->when(
                                $data['plates_to'],
                                fn (Builder $query, $plates): Builder => $query->where('required_plates', '<=', $plates),
                            );
                    }),

                // Text filter for order number
                \Filament\Tables\Filters\Filter::make('order_number')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('order_number')
                            ->label(__('orders.order_number'))
                            ->placeholder(__('orders.search_order_number')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['order_number'],
                            fn (Builder $query, $search): Builder => $query->where('order_number', 'like', "%{$search}%"),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                \Filament\Actions\Action::make('move_to_sorting')
                    ->label('نقل للفرز')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('purple')
                    ->visible(fn ($record) => $record->current_stage === 'حجز_المواد' && \Illuminate\Support\Facades\Auth::user()->role === 'sorting_manager')
                    ->action(function ($record) {
                        $record->update(['current_stage' => 'فرز']);
                        \Filament\Notifications\Notification::make()
                            ->title('تم نقل الطلب لمرحلة الفرز')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('move_to_cutting')
                    ->label('نقل للقص')
                    ->icon('heroicon-o-scissors')
                    ->color('orange')
                    ->visible(fn ($record) => $record->current_stage === 'فرز' && \Illuminate\Support\Facades\Auth::user()->role === 'cutting_manager')
                    ->action(function ($record) {
                        $record->update(['current_stage' => 'قص']);
                        \Filament\Notifications\Notification::make()
                            ->title('تم نقل الطلب لمرحلة القص')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('move_to_packaging')
                    ->label('نقل للتعبئة')
                    ->icon('heroicon-o-archive-box')
                    ->color('indigo')
                    ->visible(fn ($record) => $record->current_stage === 'قص' && in_array(\Illuminate\Support\Facades\Auth::user()->role, ['cutting_manager', 'delivery_manager']))
                    ->action(function ($record) {
                        $record->update(['current_stage' => 'تعبئة']);
                        \Filament\Notifications\Notification::make()
                            ->title('تم نقل الطلب لمرحلة التعبئة')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('move_to_delivery')
                    ->label('نقل للتسليم')
                    ->icon('heroicon-o-truck')
                    ->color('green')
                    ->visible(fn ($record) => $record->current_stage === 'تعبئة' && \Illuminate\Support\Facades\Auth::user()->role === 'delivery_manager')
                    ->action(function ($record) {
                        $record->update(['current_stage' => 'تسليم']);
                        \Filament\Notifications\Notification::make()
                            ->title('تم نقل الطلب لمرحلة التسليم')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->poll('30s'); // تحديث تلقائي كل 30 ثانية
    }
}
