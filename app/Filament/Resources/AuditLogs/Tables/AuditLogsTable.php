<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Exports\AuditLogExport;
use App\Models\AuditLog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(function () {
                return AuditLog::query()->with(['user', 'auditable']);
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('التاريخ والوقت')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable()
                    ->placeholder('غير محدد'),

                BadgeColumn::make('event_type')
                    ->label('نوع الحدث')
                    ->colors([
                        'success' => 'created',
                        'warning' => 'updated',
                        'danger' => 'deleted',
                        'primary' => ['login', 'logout'],
                        'gray' => 'viewed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'created' => 'إنشاء',
                        'updated' => 'تحديث',
                        'deleted' => 'حذف',
                        'login' => 'دخول',
                        'logout' => 'خروج',
                        'viewed' => 'عرض',
                        default => ucfirst($state)
                    })
                    ->sortable(),

                TextColumn::make('auditable_type')
                    ->label('نوع السجل')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->sortable(),

                TextColumn::make('auditable_id')
                    ->label('معرف السجل')
                    ->searchable(),

                TextColumn::make('event_description')
                    ->label('وصف الحدث')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),

                TextColumn::make('ip_address')
                    ->label('عنوان IP')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('user_agent')
                    ->label('المتصفح')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    })
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('من تاريخ'),
                        \Filament\Forms\Components\DatePicker::make('created_to')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),

                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->label('المستخدم')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('event_type')
                    ->options([
                        'created' => 'إنشاء',
                        'updated' => 'تحديث',
                        'deleted' => 'حذف',
                        'login' => 'دخول',
                        'logout' => 'خروج',
                        'viewed' => 'عرض',
                    ])
                    ->label('نوع الحدث'),

                SelectFilter::make('auditable_type')
                    ->options([
                        'App\Models\User' => 'مستخدم',
                        'App\Models\Order' => 'طلب',
                        'App\Models\Product' => 'منتج',
                        'App\Models\Transfer' => 'تحويل',
                        'App\Models\Stock' => 'مخزون',
                        'App\Models\Customer' => 'عميل',
                        'App\Models\Supplier' => 'مورد',
                        'App\Models\Warehouse' => 'مستودع',
                    ])
                    ->label('نوع السجل'),
            ])
            ->actions([
                ViewAction::make()
                    ->label('عرض التفاصيل'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->label('تصدير المحدد')
                        ->exports([
                            \Filament\Actions\Exports\Export::make()
                                ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
                                ->withFilename('selected-audit-logs-' . now()->format('Y-m-d-H-i-s'))
                        ]),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('تصدير الكل')
                    ->exports([
                        \Filament\Actions\Exports\Export::make()
                            ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
                            ->withFilename('audit-logs-' . now()->format('Y-m-d-H-i-s'))
                            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'auditable']))
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->poll('30s');
    }
}