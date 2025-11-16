<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class UserSettings extends Page
{
    use InteractsWithSchemas;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.user-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'الحساب';

    public static function getNavigationLabel(): string
    {
        return 'إعدادات الحساب';
    }

    public $name;
    public $email;
    public $theme;
    public $language;

    public function mount()
    {
        $user = Auth::user();
        if ($user) {
            $this->form->fill([
                'name' => $user->name,
                'email' => $user->email,
                'theme' => $user->theme ?? 'light',
                'language' => $user->language ?? 'en',
            ]);
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->nullable(),
                Forms\Components\Select::make('theme')
                    ->options([
                        'light' => 'فاتح',
                        'dark' => 'داكن',
                        'auto' => 'تلقائي',
                    ]),
                Forms\Components\Select::make('language')
                    ->options([
                        'en' => 'الإنجليزية',
                        'ar' => 'العربية',
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('حفظ الإعدادات')
                ->action(function () {
                    $user = Auth::user();
                    if ($user) {
                        $data = $this->form->getState();
                        $user->update($data);
                        Notification::make()
                            ->success()
                            ->title('تم حفظ الإعدادات بنجاح.')
                            ->send();
                    }
                }),
        ];
    }
}