<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class SecurityConfigValidator extends Command
{
    protected $signature = 'security:validate-config 
                          {--fix : Automatically fix configuration issues where possible}
                          {--output=console : Output format (console, json, report)}
                          {--strict : Enable strict validation mode}';

    protected $description = 'Validate security configuration and identify issues';

    private $issues = [];
    private $recommendations = [];
    private $autoFixable = [];

    public function handle()
    {
        $this->info('🔒 Starting Security Configuration Validation...');
        $this->info('Time: ' . now()->toISOString());
        
        try {
            $this->validateEnvironmentConfig();
            $this->validateApplicationConfig();
            $this->validateSecurityConfig();
            $this->validateDatabaseConfig();
            $this->validateSessionConfig();
            $this->validateCacheConfig();
            $this->validateLoggingConfig();
            $this->validateMailConfig();
            $this->validateQueueConfig();
            $this->validateFilePermissions();
            $this->validateSSLSettings();
            $this->validateMiddlewareConfig();
            
            $this->displayResults();
            $this->generateRecommendations();
            
            if ($this->option('fix')) {
                $this->autoFixIssues();
            }
            
            $this->info('✅ Security configuration validation completed!');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Configuration validation failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function validateEnvironmentConfig(): void
    {
        $this->info('🌍 Validating environment configuration...');
        
        // Check APP_DEBUG
        if (app()->environment('production') && config('app.debug')) {
            $this->addIssue('high', 'APP_DEBUG enabled in production', [
                'file' => '.env',
                'setting' => 'APP_DEBUG',
                'current' => 'true',
                'recommended' => 'false',
                'fix' => 'Set APP_DEBUG=false in production environment'
            ]);
        }
        
        // Check APP_KEY
        if (empty(config('app.key'))) {
            $this->addIssue('critical', 'APP_KEY not set', [
                'file' => '.env',
                'setting' => 'APP_KEY',
                'current' => 'empty',
                'recommended' => 'base64:generated_key',
                'fix' => 'Run: php artisan key:generate'
            ]);
        }
        
        // Check FORCE_HTTPS
        if (app()->environment('production') && !config('app.force_https', false)) {
            $this->addIssue('medium', 'HTTPS not enforced', [
                'file' => '.env',
                'setting' => 'APP_FORCE_HTTPS',
                'current' => 'false',
                'recommended' => 'true',
                'fix' => 'Set APP_FORCE_HTTPS=true in production'
            ]);
        }
        
        // Check LOG_LEVEL
        if (config('app.env') === 'production' && config('logging.level') === 'debug') {
            $this->addIssue('medium', 'Debug logging enabled in production', [
                'file' => 'config/logging.php',
                'setting' => 'logging.level',
                'current' => 'debug',
                'recommended' => 'warning',
                'fix' => 'Set LOG_LEVEL=warning in production'
            ]);
        }
    }

    private function validateApplicationConfig(): void
    {
        $this->info('⚙️ Validating application configuration...');
        
        // Check APP_URL
        $appUrl = config('app.url');
        if (!$this->isValidUrl($appUrl)) {
            $this->addIssue('high', 'Invalid APP_URL configuration', [
                'file' => 'config/app.php',
                'setting' => 'app.url',
                'current' => $appUrl ?: 'empty',
                'recommended' => 'https://yourdomain.com',
                'fix' => 'Set a valid HTTPS URL in APP_URL'
            ]);
        }
        
        // Check APP_NAME
        if (config('app.name') === 'Laravel') {
            $this->addIssue('low', 'Default application name', [
                'file' => 'config/app.php',
                'setting' => 'app.name',
                'current' => 'Laravel',
                'recommended' => 'Your Application Name',
                'fix' => 'Set a unique application name'
            ]);
        }
        
        // Check maintenance mode configuration
        if (!config('maintenance.driver', 'file')) {
            $this->addIssue('medium', 'Maintenance mode not properly configured', [
                'file' => 'config/app.php',
                'setting' => 'maintenance.driver',
                'current' => 'null',
                'recommended' => 'file or redis',
                'fix' => 'Configure maintenance mode driver'
            ]);
        }
    }

    private function validateSecurityConfig(): void
    {
        $this->info('🛡️ Validating security configuration...');
        
        // Check security middleware
        if (!config('app.security_middleware_enabled', false)) {
            $this->addIssue('high', 'Security middleware not enabled', [
                'file' => 'app/Providers/AppServiceProvider.php',
                'setting' => 'app.security_middleware_enabled',
                'current' => 'false',
                'recommended' => 'true',
                'fix' => 'Enable security middleware in AppServiceProvider'
            ]);
        }
        
        // Check rate limiting
        $apiRateLimit = config('security.api_rate_limit', 60);
        if ($apiRateLimit > 100) {
            $this->addIssue('medium', 'High API rate limit', [
                'file' => 'config/security.php',
                'setting' => 'security.api_rate_limit',
                'current' => $apiRateLimit,
                'recommended' => '60',
                'fix' => 'Reduce API rate limit to prevent abuse'
            ]);
        }
        
        // Check authentication security
        $authAttempts = config('security.auth_attempts', 5);
        if ($authAttempts > 10) {
            $this->addIssue('medium', 'High authentication attempt limit', [
                'file' => 'config/security.php',
                'setting' => 'security.auth_attempts',
                'current' => $authAttempts,
                'recommended' => '5',
                'fix' => 'Reduce authentication attempt limit'
            ]);
        }
    }

    private function validateDatabaseConfig(): void
    {
        $this->info('🗄️ Validating database configuration...');
        
        // Check database credentials in environment
        $dbHost = env('DB_HOST');
        if ($dbHost === 'localhost' || $dbHost === '127.0.0.1') {
            $this->addIssue('medium', 'Database on localhost in production', [
                'file' => '.env',
                'setting' => 'DB_HOST',
                'current' => $dbHost,
                'recommended' => 'dedicated_database_server',
                'fix' => 'Use dedicated database server in production'
            ]);
        }
        
        // Check database SSL
        if (app()->environment('production') && !config('database.connections.mysql.sslmode')) {
            $this->addIssue('high', 'Database SSL not configured', [
                'file' => 'config/database.php',
                'setting' => 'database.connections.mysql.sslmode',
                'current' => 'null',
                'recommended' => 'require',
                'fix' => 'Enable SSL mode for database connections'
            ]);
        }
        
        // Check database strict mode
        if (!config('database.connections.mysql.strict', true)) {
            $this->addIssue('medium', 'Database strict mode disabled', [
                'file' => 'config/database.php',
                'setting' => 'database.connections.mysql.strict',
                'current' => 'false',
                'recommended' => 'true',
                'fix' => 'Enable strict mode for better data integrity'
            ]);
        }
    }

    private function validateSessionConfig(): void
    {
        $this->info('🍪 Validating session configuration...');
        
        // Check session secure
        if (app()->environment('production') && !config('session.secure', false)) {
            $this->addIssue('high', 'Session secure flag not enabled', [
                'file' => 'config/session.php',
                'setting' => 'session.secure',
                'current' => 'false',
                'recommended' => 'true',
                'fix' => 'Enable secure sessions in production'
            ]);
        }
        
        // Check session HTTP only
        if (!config('session.http_only', false)) {
            $this->addIssue('high', 'Session HTTP only not enabled', [
                'file' => 'config/session.php',
                'setting' => 'session.http_only',
                'current' => 'false',
                'recommended' => 'true',
                'fix' => 'Enable HTTP only sessions'
            ]);
        }
        
        // Check session lifetime
        $sessionLifetime = config('session.lifetime', 120);
        if ($sessionLifetime > 240) {
            $this->addIssue('medium', 'Long session lifetime', [
                'file' => 'config/session.php',
                'setting' => 'session.lifetime',
                'current' => $sessionLifetime,
                'recommended' => '120',
                'fix' => 'Reduce session lifetime for better security'
            ]);
        }
    }

    private function validateCacheConfig(): void
    {
        $this->info('💾 Validating cache configuration...');
        
        // Check cache driver
        if (app()->environment('production') && config('cache.default') === 'file') {
            $this->addIssue('medium', 'Using file cache in production', [
                'file' => 'config/cache.php',
                'setting' => 'cache.default',
                'current' => 'file',
                'recommended' => 'redis or memcached',
                'fix' => 'Use Redis or Memcached for better performance'
            ]);
        }
        
        // Check cache prefix
        if (!config('cache.prefix')) {
            $this->addIssue('low', 'Cache prefix not set', [
                'file' => 'config/cache.php',
                'setting' => 'cache.prefix',
                'current' => 'null',
                'recommended' => 'unique_prefix',
                'fix' => 'Set a unique cache prefix'
            ]);
        }
    }

    private function validateLoggingConfig(): void
    {
        $this->info('📝 Validating logging configuration...');
        
        // Check logging channel
        if (!config('logging.default')) {
            $this->addIssue('medium', 'Default logging channel not set', [
                'file' => 'config/logging.php',
                'setting' => 'logging.default',
                'current' => 'null',
                'recommended' => 'daily',
                'fix' => 'Set default logging channel'
            ]);
        }
        
        // Check log level for production
        if (app()->environment('production') && config('logging.level') === 'debug') {
            $this->addIssue('medium', 'Debug logging in production', [
                'file' => 'config/logging.php',
                'setting' => 'logging.level',
                'current' => 'debug',
                'recommended' => 'warning',
                'fix' => 'Set appropriate log level for production'
            ]);
        }
    }

    private function validateMailConfig(): void
    {
        $this->info('📧 Validating mail configuration...');
        
        // Check mail encryption
        if (!config('mail.encryption') && config('mail.default') !== 'log') {
            $this->addIssue('medium', 'Mail encryption not configured', [
                'file' => 'config/mail.php',
                'setting' => 'mail.encryption',
                'current' => 'null',
                'recommended' => 'tls',
                'fix' => 'Enable TLS encryption for mail'
            ]);
        }
        
        // Check mail driver for production
        if (app()->environment('production') && config('mail.default') === 'log') {
            $this->addIssue('medium', 'Using log mail driver in production', [
                'file' => 'config/mail.php',
                'setting' => 'mail.default',
                'current' => 'log',
                'recommended' => 'smtp or ses',
                'fix' => 'Use proper mail driver for production'
            ]);
        }
    }

    private function validateQueueConfig(): void
    {
        $this->info('🔄 Validating queue configuration...');
        
        // Check queue connection
        if (app()->environment('production') && config('queue.default') === 'sync') {
            $this->addIssue('high', 'Using sync queue in production', [
                'file' => 'config/queue.php',
                'setting' => 'queue.default',
                'current' => 'sync',
                'recommended' => 'redis or database',
                'fix' => 'Use Redis or database queue for production'
            ]);
        }
    }

    private function validateFilePermissions(): void
    {
        $this->info('📁 Validating file permissions...');
        
        $filesToCheck = [
            storage_path('app') => '755',
            storage_path('framework') => '755',
            storage_path('logs') => '755',
            base_path('.env') => '600',
            base_path('bootstrap/cache') => '755'
        ];
        
        foreach ($filesToCheck as $file => $expectedPerm) {
            if (File::exists($file)) {
                $currentPerm = substr(sprintf('%o', fileperms($file)), -3);
                
                if ($file === base_path('.env')) {
                    if ($currentPerm !== '600') {
                        $this->addIssue('high', 'Insecure .env file permissions', [
                            'file' => '.env',
                            'setting' => 'file_permissions',
                            'current' => $currentPerm,
                            'recommended' => '600',
                            'fix' => 'chmod 600 .env'
                        ]);
                    }
                } else {
                    if ($currentPerm !== $expectedPerm) {
                        $this->addIssue('medium', "Insecure file permissions: {$file}", [
                            'file' => $file,
                            'setting' => 'file_permissions',
                            'current' => $currentPerm,
                            'recommended' => $expectedPerm,
                            'fix' => "chmod {$expectedPerm} {$file}"
                        ]);
                    }
                }
            }
        }
    }

    private function validateSSLSettings(): void
    {
        $this->info('🔐 Validating SSL/TLS settings...');
        
        if (app()->environment('production')) {
            // This would check SSL configuration via web server
            // For now, we'll validate that HTTPS is enforced
            
            $appUrl = config('app.url');
            if (strpos($appUrl, 'http://') === 0) {
                $this->addIssue('critical', 'HTTPS not used for application URL', [
                    'file' => '.env',
                    'setting' => 'APP_URL',
                    'current' => $appUrl,
                    'recommended' => 'https://yourdomain.com',
                    'fix' => 'Update APP_URL to use HTTPS'
                ]);
            }
        }
    }

    private function validateMiddlewareConfig(): void
    {
        $this->info('🔄 Validating middleware configuration...');
        
        // Check if security middleware is registered
        $middlewareFile = app_path('Http/Kernel.php');
        
        if (File::exists($middlewareFile)) {
            $content = File::get($middlewareFile);
            
            if (strpos($content, 'SecurityHeaders') === false) {
                $this->addIssue('high', 'SecurityHeaders middleware not registered', [
                    'file' => 'app/Http/Kernel.php',
                    'setting' => 'middleware',
                    'current' => 'not_registered',
                    'recommended' => 'registered',
                    'fix' => 'Register SecurityHeaders middleware in Kernel'
                ]);
            }
            
            if (strpos($content, 'PreventCommonAttacks') === false) {
                $this->addIssue('high', 'PreventCommonAttacks middleware not registered', [
                    'file' => 'app/Http/Kernel.php',
                    'setting' => 'middleware',
                    'current' => 'not_registered',
                    'recommended' => 'registered',
                    'fix' => 'Register PreventCommonAttacks middleware in Kernel'
                ]);
            }
        }
    }

    private function addIssue(string $severity, string $title, array $details): void
    {
        $issue = array_merge([
            'id' => Str::uuid()->toString(),
            'severity' => $severity,
            'title' => $title,
            'detected_at' => now()->toISOString()
        ], $details);
        
        $this->issues[] = $issue;
        
        // Mark as auto-fixable if fix is available
        if (isset($issue['fix']) && !empty($issue['fix'])) {
            $this->autoFixable[] = $issue;
        }
    }

    private function displayResults(): void
    {
        $this->newLine();
        $this->info('📊 CONFIGURATION VALIDATION RESULTS');
        $this->info('====================================');
        
        $severityCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        
        foreach ($this->issues as $issue) {
            $severityCounts[$issue['severity']]++;
        }
        
        $this->table(
            ['Severity', 'Count'],
            [
                ['🔴 Critical', $severityCounts['critical']],
                ['🟠 High', $severityCounts['high']],
                ['🟡 Medium', $severityCounts['medium']],
                ['🟢 Low', $severityCounts['low']],
                ['📊 Total Issues', count($this->issues)]
            ]
        );
        
        if (!empty($this->issues)) {
            $this->newLine();
            $this->warn('⚠️  CONFIGURATION ISSUES FOUND:');
            $this->warn('================================');
            
            foreach ($this->issues as $issue) {
                $severityIcon = match($issue['severity']) {
                    'critical' => '🔴',
                    'high' => '🟠',
                    'medium' => '🟡',
                    'low' => '🟢',
                    default => '⚪'
                };
                
                $this->line("{$severityIcon} [{$issue['severity']}] {$issue['title']}");
                $this->line("   📁 File: {$issue['file']}");
                $this->line("   📝 Setting: {$issue['setting']}");
                $this->line("   🔄 Current: {$issue['current']}");
                $this->line("   ✅ Recommended: {$issue['recommended']}");
                if (isset($issue['fix'])) {
                    $this->line("   🛠️  Fix: {$issue['fix']}");
                }
                $this->line('');
            }
        } else {
            $this->info('✅ No configuration issues found!');
        }
    }

    private function generateRecommendations(): void
    {
        $this->newLine();
        $this->info('💡 SECURITY RECOMMENDATIONS:');
        $this->info('============================');
        
        $recommendations = [
            'Regular Updates' => 'Keep Laravel, PHP, and dependencies updated regularly',
            'Backup Strategy' => 'Implement automated backups with encryption',
            'Monitoring' => 'Set up security monitoring and alerting',
            'SSL Certificate' => 'Use valid SSL certificates and enable HSTS',
            'Access Control' => 'Implement proper role-based access control',
            'Input Validation' => 'Validate and sanitize all user inputs',
            'Output Encoding' => 'Encode output to prevent XSS attacks',
            'File Upload Security' => 'Validate file uploads and restrict file types',
            'Session Management' => 'Implement secure session management',
            'Rate Limiting' => 'Implement rate limiting for sensitive endpoints'
        ];
        
        foreach ($recommendations as $title => $description) {
            $this->line("• {$title}: {$description}");
        }
    }

    private function autoFixIssues(): void
    {
        if (empty($this->autoFixable)) {
            $this->info('ℹ️ No auto-fixable issues found');
            return;
        }
        
        $this->newLine();
        $this->info('🔧 ATTEMPTING AUTO-FIXES...');
        $this->info('==========================');
        
        $fixed = 0;
        $failed = 0;
        
        foreach ($this->autoFixable as $issue) {
            $this->line("🔄 Fixing: {$issue['title']}...");
            
            try {
                $success = $this->applyFix($issue);
                if ($success) {
                    $this->line("   ✅ Fixed successfully");
                    $fixed++;
                } else {
                    $this->line("   ❌ Fix failed - manual intervention required");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->line("   ❌ Fix error: " . $e->getMessage());
                $failed++;
            }
        }
        
        $this->newLine();
        $this->info("📊 Auto-fix summary: {$fixed} fixed, {$failed} failed");
        
        if ($failed > 0) {
            $this->warn('⚠️  Some issues could not be auto-fixed. Manual intervention required.');
        }
    }

    private function applyFix(array $issue): bool
    {
        $fix = $issue['fix'] ?? '';
        
        if (strpos($fix, 'php artisan') !== false) {
            // Execute artisan command
            $command = str_replace('php artisan ', '', $fix);
            Artisan::call($command);
            return true;
        }
        
        if (strpos($fix, 'chmod') !== false) {
            // Change file permissions
            $parts = explode(' ', $fix);
            if (count($parts) >= 2) {
                $perm = $parts[1];
                $file = $parts[2];
                return chmod($file, octdec($perm));
            }
        }
        
        if (str_starts_with($fix, 'Set ')) {
            // Environment variable change
            $envMatch = preg_match('/Set ([A-Z_]+)=(.+)/', $fix, $matches);
            if ($envMatch) {
                $key = $matches[1];
                $value = $matches[2];
                return $this->updateEnvVariable($key, $value);
            }
        }
        
        return false;
    }

    private function updateEnvVariable(string $key, string $value): bool
    {
        $envFile = base_path('.env');
        
        if (!File::exists($envFile)) {
            return false;
        }
        
        $content = File::get($envFile);
        
        // Check if key exists
        if (preg_match("/^{$key}=.*/m", $content)) {
            // Update existing key
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            // Add new key
            $content .= "\n{$key}={$value}\n";
        }
        
        return File::put($envFile, $content) !== false;
    }

    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && 
               (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0);
    }
}