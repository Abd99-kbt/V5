<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use BackedEnum;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'إدارة المستخدمين';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'المستخدمين';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('users.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('username')
                    ->label(__('users.username'))
                    ->required()
                    ->unique('users', 'username', ignoreRecord: true)
                    ->maxLength(255)
                    ->rules(['regex:/^[a-zA-Z0-9_\-\p{Arabic}]+$/u'])
                    ->helperText(__('users.username_helper')),
                Forms\Components\TextInput::make('email')
                    ->label(__('users.email'))
                    ->email()
                    ->nullable()
                    ->unique('users', 'email', ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText(__('users.email_optional')),
                Forms\Components\TextInput::make('password')
                    ->label(__('users.password'))
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),
                Forms\Components\Select::make('roles')
                    ->label(__('users.roles'))
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => __('users.roles.' . $record->name) ?: $record->name)
                    ->preload(),
                Forms\Components\TextInput::make('theme')
                    ->label(__('users.theme'))
                    ->maxLength(255),
                Forms\Components\TextInput::make('language')
                    ->label(__('users.language'))
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('users.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('username')
                    ->label(__('users.username'))
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('users.email'))
                    ->searchable()
                    ->placeholder(__('users.email_optional')),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label(__('users.roles'))
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(function ($state) {
                        return __('users.roles.' . $state) ?: $state;
                    }),
                Tables\Columns\TextColumn::make('theme')
                    ->label(__('users.theme')),
                Tables\Columns\TextColumn::make('language')
                    ->label(__('users.language')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('users.created_at'))
                    ->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('users.updated_at'))
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label(__('users.roles'))
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => __('users.roles.' . $record->name) ?: $record->name)
                    ->multiple(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        return $user->hasPermissionTo('view users') || $user->hasPermissionTo('manage users');
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        return $user->hasPermissionTo('create users') || $user->hasPermissionTo('manage users');
    }

    public static function canEdit($record): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        return $user->hasPermissionTo('edit users') || $user->hasPermissionTo('manage users');
    }

    public static function canDelete($record): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        return $user->hasPermissionTo('delete users') || $user->hasPermissionTo('manage users');
    }
}