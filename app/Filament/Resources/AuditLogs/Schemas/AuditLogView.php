<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;

class AuditLogView
{
    public static function configure(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('معلومات الحدث')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('التاريخ والوقت')
                                    ->dateTime('Y-m-d H:i:s'),

                                TextEntry::make('user.name')
                                    ->label('المستخدم')
                                    ->placeholder('غير محدد'),

                                TextEntry::make('event_type')
                                    ->label('نوع الحدث')
                                    ->formatStateUsing(fn (string $state): string => match($state) {
                                        'created' => 'إنشاء',
                                        'updated' => 'تحديث',
                                        'deleted' => 'حذف',
                                        'login' => 'دخول',
                                        'logout' => 'خروج',
                                        'viewed' => 'عرض',
                                        default => ucfirst($state)
                                    }),

                                TextEntry::make('auditable_type')
                                    ->label('نوع السجل')
                                    ->formatStateUsing(fn (string $state): string => class_basename($state)),

                                TextEntry::make('auditable_id')
                                    ->label('معرف السجل'),

                                TextEntry::make('ip_address')
                                    ->label('عنوان IP'),

                                TextEntry::make('user_agent')
                                    ->label('المتصفح')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('وصف الحدث')
                    ->schema([
                        TextEntry::make('event_description')
                            ->label('الوصف')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => !empty($record->event_description)),

                Section::make('القيم السابقة')
                    ->schema([
                        ViewEntry::make('old_values')
                            ->label('')
                            ->view('filament.infolists.entries.json-view')
                            ->viewData(['data' => fn ($record) => $record->old_values]),
                    ])
                    ->visible(fn ($record) => !empty($record->old_values)),

                Section::make('القيم الجديدة')
                    ->schema([
                        ViewEntry::make('new_values')
                            ->label('')
                            ->view('filament.infolists.entries.json-view')
                            ->viewData(['data' => fn ($record) => $record->new_values]),
                    ])
                    ->visible(fn ($record) => !empty($record->new_values)),

                Section::make('البيانات الإضافية')
                    ->schema([
                        ViewEntry::make('metadata')
                            ->label('')
                            ->view('filament.infolists.entries.json-view')
                            ->viewData(['data' => fn ($record) => $record->metadata]),
                    ])
                    ->visible(fn ($record) => !empty($record->metadata)),

                Section::make('معلومات الجلسة')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('session_id')
                                    ->label('معرف الجلسة'),

                                TextEntry::make('created_at')
                                    ->label('تاريخ الإنشاء')
                                    ->dateTime(),
                            ]),
                    ])
                    ->visible(fn ($record) => !empty($record->session_id)),
            ]);
    }
}