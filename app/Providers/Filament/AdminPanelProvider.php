<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Navigation\NavigationGroup;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\Facades\App;

class AdminPanelProvider extends PanelProvider
{
    protected function getNavigationGroups(): array
    {
        $groups = [];

        // Check if user has inventory management permissions
        if (\Illuminate\Support\Facades\Auth::check() && collect([
            'view materials', 'create materials', 'edit materials', 'delete materials', 'manage materials',
            'view warehouses', 'create warehouses', 'edit warehouses', 'delete warehouses', 'manage warehouses',
            'manage stock', 'view stock alerts', 'manage stock alerts'
        ])->contains(fn($perm) => \Illuminate\Support\Facades\Auth::user()->can($perm))) {
            $groups[] = NavigationGroup::make('إدارة المخزون')
                ->items([
                    \App\Filament\Resources\Products\ProductResource::class,
                    \App\Filament\Resources\Categories\CategoryResource::class,
                    \App\Filament\Resources\Suppliers\SupplierResource::class,
                    \App\Filament\Resources\Warehouses\WarehouseResource::class,
                    \App\Filament\Resources\Stocks\StockResource::class,
                    \App\Filament\Resources\StockAlerts\StockAlertResource::class,
                ]);
        }

        // Check if user has sales and orders permissions
        if (\Illuminate\Support\Facades\Auth::check() && collect([
            'view orders', 'create orders', 'edit orders', 'delete orders', 'manage orders', 'process orders',
            'view customers', 'create customers', 'edit customers', 'delete customers', 'manage customers',
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices', 'manage invoices'
        ])->contains(fn($perm) => \Illuminate\Support\Facades\Auth::user()->can($perm))) {
            $groups[] = NavigationGroup::make('المبيعات والطلبات')
                ->items([
                    \App\Filament\Resources\Orders\OrderResource::class,
                    \App\Filament\Resources\Customers\CustomerResource::class,
                    \App\Filament\Resources\Invoices\InvoiceResource::class,
                ]);
        }

        // Check if user has operations permissions
        if (\Illuminate\Support\Facades\Auth::check() && collect([
            'view transfers', 'create transfers', 'manage transfers',
            'view wastes', 'manage wastes',
            'view work stages', 'manage work stages',
            'view approvals', 'manage approvals',
            'view deliveries', 'create deliveries', 'edit deliveries', 'manage deliveries',
            'view audit logs', 'manage audit logs'
        ])->contains(fn($perm) => \Illuminate\Support\Facades\Auth::user()->can($perm))) {
            $groups[] = NavigationGroup::make('العمليات')
                ->items([
                    \App\Filament\Resources\Transfers\TransferResource::class,
                    \App\Filament\Resources\Wastes\WasteResource::class,
                    \App\Filament\Resources\WorkStages\WorkStageResource::class,
                    \App\Filament\Resources\Approvals\ApprovalResource::class,
                    \App\Filament\Resources\AuditLogs\AuditLogResource::class,
                ]);
        }

        // Check if user has user management permissions
        if (\Illuminate\Support\Facades\Auth::check() && collect([
            'view users', 'create users', 'edit users', 'delete users', 'manage users'
        ])->contains(fn($perm) => \Illuminate\Support\Facades\Auth::user()->can($perm))) {
            $groups[] = NavigationGroup::make('إدارة المستخدمين')
                ->items([
                    \App\Filament\Resources\Users\UserResource::class,
                ]);
        }

        return $groups;
    }

    public function panel(Panel $panel): Panel
    {
        App::setLocale('ar');

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->darkMode()
            ->brandName(__('warehouse.warehouse_management'))
            ->favicon(asset('favicon.ico'))
            ->breadcrumbs(true)
            ->globalSearch()
            ->authGuard('username')
            ->authPasswordBroker('users')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
                \App\Filament\Pages\UserSettings::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->navigationGroups($this->getNavigationGroups())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->colors([
                'primary' => Color::Amber,
                'secondary' => Color::Gray,
                'success' => Color::Green,
                'warning' => Color::Yellow,
                'danger' => Color::Red,
                'info' => Color::Blue,
            ]);
    }
}