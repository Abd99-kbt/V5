<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Filament\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use BackedEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $label = 'سجلات التدقيق';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    // protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 100;

    public static function getNavigationLabel(): string
    {
        return 'سجلات التدقيق';
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole('admin') ?? false;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['admin', 'manager', 'auditor']) ?? false;
    }

    public static function canView($record): bool
    {
        return Auth::user()?->hasRole(['admin', 'manager', 'auditor']) ?? false;
    }
}