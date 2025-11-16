<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register dummy encrypter for development (no encryption)
        $this->registerDummyEncrypter();
        
        // Register security services
        $this->registerSecurityServices();
        
        // Register monitoring services
        $this->registerMonitoringServices();
        
        // Register validation services
        $this->registerValidationServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureSecurity();
        $this->configureRateLimiters();
        $this->configureGates();
        $this->configureLogging();
        $this->configureDatabaseSecurity();
        $this->configureSessionSecurity();
        $this->configureCSP();
        $this->configureValidationRules();
        $this->registerSecurityMiddleware();
    }

    /**
     * Register dummy encrypter for development (no encryption)
     */
    protected function registerDummyEncrypter(): void
    {
        $this->app->singleton('encrypter', function ($app) {
            return new class implements \Illuminate\Contracts\Encryption\Encrypter {
                public function encrypt($value, $serialize = true)
                {
                    return $serialize ? serialize($value) : (string) $value;
                }

                public function decrypt($payload, $unserialize = true)
                {
                    return $unserialize ? unserialize($payload) : (string) $payload;
                }

                public function encryptString($value)
                {
                    return (string) $value;
                }

                public function decryptString($payload)
                {
                    return (string) $payload;
                }

                public function getKey()
                {
                    return 'dummy-development-key';
                }

                public function getAllKeys()
                {
                    return ['dummy-development-key'];
                }

                public function getPreviousKeys()
                {
                    return [];
                }
            };
        });
    }

    /**
     * Register security-related services
     */
    protected function registerSecurityServices(): void
    {
        $this->app->singleton('security.monitor', function ($app) {
            return new class {
                public function logSecurityEvent(string $event, array $data = []): void
                {
                    Log::channel('security')->info($event, array_merge($data, [
                        'timestamp' => now()->toISOString(),
                        'memory_usage' => memory_get_usage(true),
                        'peak_memory' => memory_get_peak_usage(true),
                    ]));
                }

                public function incrementThreatScore(string $identifier, int $score = 1): int
                {
                    $key = "threat_score:" . hash('sha256', $identifier);
                    $current = Cache::get($key, 0);
                    $newScore = $current + $score;
                    Cache::put($key, $newScore, now()->addHours(2));
                    return $newScore;
                }

                public function shouldBlock(string $identifier, int $threshold = 15): bool
                {
                    $key = "threat_score:" . hash('sha256', $identifier);
                    return Cache::get($key, 0) >= $threshold;
                }
            };
        });
    }

    /**
     * Register monitoring services
     */
    protected function registerMonitoringServices(): void
    {
        $this->app->singleton('security.alerts', function ($app) {
            return new class {
                public function sendAlert(string $level, string $message, array $context = []): void
                {
                    if (config('security.enable_real_time_alerts', false)) {
                        Log::channel('security')->log($level, $message, $context);
                    }
                }

                public function criticalSecurityEvent(string $event, array $data = []): void
                {
                    $this->sendAlert('critical', "Critical security event: {$event}", $data);
                    
                    // Auto-block high-risk activities
                    if (isset($data['ip']) && $this->isHighRiskActivity($data)) {
                        $this->blockIP($data['ip'], $event);
                    }
                }

                protected function isHighRiskActivity(array $data): bool
                {
                    $highRiskEvents = [
                        'MULTIPLE_FAILED_LOGINS',
                        'SQL_INJECTION_ATTEMPT',
                        'BRUTE_FORCE_ATTACK',
                        'ADMIN_AREA_ACCESS_ATTEMPT',
                        'SUSPICIOUS_FILE_UPLOAD'
                    ];

                    return in_array($data['event'] ?? '', $highRiskEvents);
                }

                protected function blockIP(string $ip, string $reason): void
                {
                    Cache::put("temp_block:{$ip}", [
                        'reason' => $reason,
                        'blocked_at' => now(),
                        'expires_at' => now()->addHours(24)
                    ], now()->addHours(24));
                }
            };
        });
    }

    /**
     * Register validation services
     */
    protected function registerValidationServices(): void
    {
        $this->app->singleton('security.validator', function ($app) {
            return new class {
                public function validateInput(string $input, string $type = 'general'): bool
                {
                    switch ($type) {
                        case 'email':
                            return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
                        case 'url':
                            return filter_var($input, FILTER_VALIDATE_URL) !== false;
                        case 'ip':
                            return filter_var($input, FILTER_VALIDATE_IP) !== false;
                        case 'general':
                        default:
                            return !$this->containsMaliciousPatterns($input);
                    }
                }

                protected function containsMaliciousPatterns(string $input): bool
                {
                    $patterns = [
                        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
                        '/javascript:/i',
                        '/on\w+\s*=/i',
                        '/\b(union|select|insert|update|delete|drop|create|alter)\b/i',
                        '/[;&|`$(){}\[\]]/',
                    ];

                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $input)) {
                            return true;
                        }
                    }

                    return false;
                }

                public function sanitizeInput(string $input): string
                {
                    // Remove null bytes
                    $input = str_replace("\0", '', $input);
                    
                    // Remove control characters
                    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
                    
                    // Trim excessive whitespace
                    $input = preg_replace('/\s+/', ' ', $input);
                    
                    // HTML entity encode
                    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            };
        });
    }

    /**
     * Configure security settings
     */
    protected function configureSecurity(): void
    {
        // Set secure defaults for production
        if (app()->environment('production')) {
            // Force HTTPS if configured and not in local environment
            // Temporarily disabled for testing
            // if (config('app.force_https', true) && !app()->environment('local')) {
            //     URL::forceScheme('https');
            // }

            // Set secure cookie settings
            config([
                'session.secure' => true,
                'session.http_only' => true,
                'session.same_site' => 'strict',
            ]);
        }

        // Configure trusted proxies for security
        if (config('app.trusted_proxies.enabled', false)) {
            try {
                Request::setTrustedProxies(
                    config('app.trusted_proxies.ips', []),
                    Request::HEADER_X_FORWARDED_FOR |
                    Request::HEADER_X_FORWARDED_HOST |
                    Request::HEADER_X_FORWARDED_PORT |
                    Request::HEADER_X_FORWARDED_PROTO |
                    Request::HEADER_X_FORWARDED_AWS_ELB
                );
            } catch (\Exception $e) {
                Log::warning('Failed to set trusted proxies', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Configure rate limiters
     */
    protected function configureRateLimiters(): void
    {
        // API rate limiting
        RateLimiter::for('api', function (Request $request) {
            return [
                Limit::perMinute(config('security.api_rate_limit', 60)),
                Limit::perHour(config('security.api_hourly_limit', 1000)),
            ];
        });

        // Authentication rate limiting
        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinutes(15, config('security.auth_attempts', 5)),
                Limit::perDay(config('security.auth_daily_limit', 20)),
            ];
        });

        // Admin area rate limiting
        RateLimiter::for('admin', function (Request $request) {
            return [
                Limit::perMinute(30),
                Limit::perHour(config('security.admin_hourly_limit', 200)),
            ];
        });

        // File upload rate limiting
        RateLimiter::for('uploads', function (Request $request) {
            return [
                Limit::perHour(config('security.upload_hourly_limit', 10)),
                Limit::perDay(config('security.upload_daily_limit', 50)),
            ];
        });
    }

    /**
     * Configure authorization gates
     */
    protected function configureGates(): void
    {
        // Admin access gate
        Gate::define('admin-access', function ($user) {
            return $user && $user->hasRole(['admin', 'super_admin']);
        });

        // Security management gate
        Gate::define('manage-security', function ($user) {
            return $user && $user->hasRole(['admin', 'security_admin']);
        });

        // Sensitive data access gate
        Gate::define('sensitive-data', function ($user) {
            return $user && $user->hasAnyPermission([
                'view_sensitive_data',
                'manage_sensitive_data'
            ]);
        });

        // Security logs access gate
        Gate::define('view-security-logs', function ($user) {
            return $user && $user->hasAnyPermission([
                'view_security_logs',
                'manage_security_logs'
            ]);
        });
    }

    /**
     * Configure logging
     */
    protected function configureLogging(): void
    {
        // Configure security log channel
        Log::channel('security')->info('Application security initialized', [
            'environment' => app()->environment(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'timestamp' => now()->toISOString()
        ]);

        // Monitor critical events
        $this->app->terminating(function () {
            if (config('security.monitor_memory_usage', true)) {
                $memoryUsage = memory_get_usage(true);
                $peakMemory = memory_get_peak_usage(true);
                
                if ($memoryUsage > config('security.memory_threshold', 134217728)) { // 128MB
                    Log::channel('security')->warning('High memory usage detected', [
                        'memory_usage' => $memoryUsage,
                        'peak_memory' => $peakMemory,
                        'memory_limit' => ini_get('memory_limit')
                    ]);
                }
            }
        });
    }

    /**
     * Configure database security
     */
    protected function configureDatabaseSecurity(): void
    {
        // Enable query logging in production for monitoring
        if (app()->environment('production') && config('security.log_queries', false)) {
            DB::listen(function ($query) {
                $time = $query->time;
                $sql = $query->sql;
                
                // Log slow queries
                if ($time > config('security.slow_query_threshold', 1000)) {
                    Log::channel('security')->warning('Slow query detected', [
                        'sql' => $sql,
                        'bindings' => $query->bindings,
                        'time' => $time,
                        'connection' => $query->connectionName
                    ]);
                }

                // Log queries containing sensitive keywords
                if (preg_match('/\b(password|token|secret|key)\b/i', $sql)) {
                    Log::channel('security')->alert('Sensitive query detected', [
                        'sql' => '[REDACTED]',
                        'time' => $time,
                        'connection' => $query->connectionName
                    ]);
                }
            });
        }

        // Configure database security settings
        config([
            'database.connections.mysql.strict' => true,
            'database.connections.mysql.modes' => [
                'STRICT_TRANS_TABLES',
                'NO_ZERO_DATE',
                'NO_ZERO_IN_DATE',
                'ERROR_FOR_DIVISION_BY_ZERO',
                'NO_AUTO_CREATE_USER'
            ]
        ]);
    }

    /**
     * Configure session security
     */
    protected function configureSessionSecurity(): void
    {
        // Configure secure session settings (disabled for development)
        config([
            'session.lifetime' => config('security.session_lifetime', 120),
            'session.expire_on_close' => config('security.expire_on_close', true),
            'session.encrypt' => false, // Disabled for development
            'session.same_site' => 'lax', // Less strict for development
        ]);

        // Regenerate session ID periodically
        if (config('security.regenerate_session_id', true)) {
            $this->app->terminating(function () {
                if (session()->has('last_regeneration')) {
                    $lastRegeneration = session()->get('last_regeneration');
                    if (time() - $lastRegeneration > config('security.session_regeneration_interval', 900)) {
                        session()->regenerate();
                        session()->put('last_regeneration', time());
                    }
                } else {
                    session()->put('last_regeneration', time());
                }
            });
        }
    }

    /**
     * Configure Content Security Policy
     */
    protected function configureCSP(): void
    {
        // CSP will be handled by the SecurityHeaders middleware
        // Here we can configure CSP policies based on environment
        if (app()->environment('production')) {
            config([
                'app.csp_enabled' => true,
                'app.csp_report_only' => false,
                'app.csp_policy' => $this->getProductionCSPPolicy(),
            ]);
        } else {
            config([
                'app.csp_enabled' => true,
                'app.csp_report_only' => true, // Report-only mode in development
                'app.csp_policy' => $this->getDevelopmentCSPPolicy(),
            ]);
        }
    }

    protected function getProductionCSPPolicy(): string
    {
        return "default-src 'self'; " .
               "script-src 'self' 'strict-dynamic'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https:; " .
               "connect-src 'self' https:; " .
               "object-src 'none'; " .
               "frame-ancestors 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self';";
    }

    protected function getDevelopmentCSPPolicy(): string
    {
        return "default-src 'self' 'unsafe-inline' 'unsafe-eval' data:; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self' ws: wss:;";
    }

    /**
     * Configure validation rules
     */
    protected function configureValidationRules(): void
    {
        // Custom validation rules for security
        Validator::extend('secure_password', function ($attribute, $value, $parameters, $validator) {
            return $this->validateSecurePassword($value);
        });

        Validator::extend('no_malicious_content', function ($attribute, $value, $parameters, $validator) {
            return !app('security.validator')->containsMaliciousPatterns($value);
        });

        Validator::extend('allowed_file_type', function ($attribute, $value, $parameters, $validator) {
            return $this->validateFileType($value, $parameters);
        });

        // Custom validation messages
        Validator::replacer('secure_password', function ($message, $attribute, $rule, $parameters) {
            return "The {$attribute} must be at least 8 characters long and contain uppercase, lowercase, number, and special character.";
        });

        Validator::replacer('no_malicious_content', function ($message, $attribute, $rule, $parameters) {
            return "The {$attribute} contains potentially malicious content.";
        });
    }

    protected function validateSecurePassword(string $password): bool
    {
        return strlen($password) >= 8 &&
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[0-9]/', $password) &&
               preg_match('/[^A-Za-z0-9]/', $password);
    }

    protected function validateFileType($file, array $allowedTypes): bool
    {
        if (!$file || !$file->isValid()) {
            return false;
        }

        $extension = $file->getClientOriginalExtension();
        return in_array(strtolower($extension), array_map('strtolower', $allowedTypes));
    }

    /**
     * Register security middleware
     */
    protected function registerSecurityMiddleware(): void
    {
        // This is where you'd register middleware in a service provider
        // Middleware is typically registered in the HTTP kernel
        // but we can configure middleware options here
        
        config([
            'app.security_middleware_enabled' => true,
            'app.cors_enabled' => config('security.enable_cors', false),
            'app.cors_origins' => config('security.allowed_origins', []),
            'app.cors_methods' => config('security.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE']),
            'app.cors_headers' => config('security.allowed_headers', [
                'Origin',
                'Content-Type',
                'X-Auth-Token',
                'Authorization',
                'X-Requested-With'
            ]),
        ]);
    }
}
