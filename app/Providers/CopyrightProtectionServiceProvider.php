<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use App\Services\WatermarkService;

class CopyrightProtectionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(WatermarkService::class, function ($app) {
            return new WatermarkService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Add copyright notice to all views
        View::composer('*', function ($view) {
            $copyrightNotice = $this->getCopyrightNotice();
            $view->with('copyright_notice', $copyrightNotice);
        });

        // Register custom Blade directive for copyright
        Blade::directive('copyright', function ($expression) {
            return "<?php echo app(App\Services\WatermarkService::class)->addLicenseWatermark(\Illuminate\Support\Facades\View::yieldContent(), {$expression}); ?>";
        });

        // Add watermark to HTML responses
        $this->app['events']->listen('creating: *', function ($model) {
            // This would be for model events if needed
        });

        // Register copyright middleware
        $this->app['router']->aliasMiddleware('copyright', \App\Http\Middleware\CopyrightMiddleware::class);
    }

    /**
     * Get copyright notice
     */
    protected function getCopyrightNotice()
    {
        return [
            'text' => '© ' . date('Y') . ' ' . config('app.name') . '. All rights reserved.',
            'license' => 'This software is licensed to: Authorized User',
            'warning' => 'Unauthorized use, reproduction, or distribution is strictly prohibited.',
            'company' => config('license.company_name', 'Your Company Name'),
            'registration' => config('license.copyright_registration', 'Copyright Registration #123456'),
        ];
    }
}