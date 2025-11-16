<?php

return [
    'panels' => [
        'admin' => [
            'path' => env('FILAMENT_PATH', '/admin'),
            'auth' => [
                'guard' => 'web',
                'login_route' => '/admin/login',
                'logout_route' => '/admin/logout',
                'login_controller' => \App\Http\Controllers\Auth\FilamentLoginController::class,
            ],
            'login' => [
                'view' => 'filament.pages.login',
            ],
            'middleware' => [
                'auth' => [],
            ],
            'widgets' => [
                'dashboard' => [
                    'filters' => [],
                ],
            ],
        ],
    ],

    'resources' => [],

    'widgets' => [
        'dashboard' => [
            'class' => \App\Filament\Pages\Dashboard::class,
        ],
    ],

    'plugins' => [],

    'development' => [],

    'cache' => [
        'allowed' => env('FILAMENT_CACHE', false),
        'duration' => env('FILAMENT_CACHE_DURATION', 60 * 60 * 24),
    ],

    'health' => [
        'endpoint' => env('FILAMENT_HEALTH_ENDPOINT'),
    ],

    'assets' => [
        'custom' => [
            [
                'asset' => resource_path('css/custom-filament.css'),
                'path' => 'custom-filament.css',
            ],
        ],
    ],

    'theme' => [
        'colors' => [
            'primary' => [
                '50' => '#eff6ff',
                '100' => '#dbeafe',
                '200' => '#bfdbfe',
                '300' => '#93c5fd',
                '400' => '#60a5fa',
                '500' => '#3b82f6',
                '600' => '#2563eb',
                '700' => '#1d4ed8',
                '800' => '#1e40af',
                '900' => '#1e3a8a',
                '950' => '#172554',
            ],
        ],
    ],

    'pages' => [
        'dashboard' => \App\Filament\Pages\Dashboard::class,
    ],

    'support' => [
        'email' => env('FILAMENT_SUPPORT_EMAIL'),
    ],
];