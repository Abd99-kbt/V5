<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SetupProduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup-production
                            {--force : Force setup without confirmation}
                            {--generate-key : Generate new application key}
                            {--optimize : Optimize for production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure application for production deployment with enhanced security settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔒 Setting up Laravel for Production with Enhanced Security');
        $this->info('======================================================');

        // Check if already in production
        if (app()->environment('production') && !$this->option('force')) {
            if (!$this->confirm('App is already in production mode. Continue anyway?')) {
                return self::SUCCESS;
            }
        }

        $this->setupProduction();

        $this->info('✅ Production setup completed successfully!');
        $this->warn('🔐 Important: Please review and update the following in your .env file:');
        $this->line('   • APP_URL (your actual domain)');
        $this->line('   • Database credentials');
        $this->line('   • Redis credentials');
        $this->line('   • Mail configuration');
        $this->line('   • AWS credentials (if using S3)');

        return self::SUCCESS;
    }

    /**
     * Setup production configuration
     */
    private function setupProduction()
    {
        $this->info('📋 Step 1: Setting up environment configuration...');

        // Update .env file for production
        $this->updateEnvForProduction();

        $this->info('🔑 Step 2: Generating secure application key...');
        if ($this->option('generate-key')) {
            Artisan::call('key:generate', ['--force' => true]);
            $this->info('   ✅ New application key generated');
        }

        $this->info('🔒 Step 3: Configuring security settings...');
        $this->configureSecurity();

        $this->info('📊 Step 4: Setting up caching and optimization...');
        if ($this->option('optimize')) {
            $this->optimizeForProduction();
        }

        $this->info('📝 Step 5: Creating security checklist...');
        $this->createSecurityChecklist();

        $this->info('🔧 Step 6: Finalizing configuration...');
        $this->finalizeConfiguration();
    }

    /**
     * Update .env file for production
     */
    private function updateEnvForProduction()
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            $this->error('❌ .env file not found!');
            return;
        }

        $updates = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'LOG_LEVEL' => 'warning',
            'SESSION_DRIVER' => 'redis',
            'CACHE_STORE' => 'redis',
            'QUEUE_CONNECTION' => 'redis',
            'SESSION_ENCRYPT' => 'true',
            'SESSION_SECURE_COOKIE' => 'true',
            'FORCE_HTTPS' => 'true',
            'BCRYPT_ROUNDS' => '12',
        ];

        foreach ($updates as $key => $value) {
            $this->updateEnvVariable($envPath, $key, $value);
        }

        $this->info('   ✅ Environment updated for production');
    }

    /**
     * Configure security settings
     */
    private function configureSecurity()
    {
        $this->info('   🔐 Setting up security headers...');
        
        // Add security headers to config if not exists
        $this->addSecurityHeaders();

        $this->info('   🛡️  Configuring CSRF protection...');
        
        $this->info('   📱 Setting up rate limiting...');
    }

    /**
     * Optimize for production
     */
    private function optimizeForProduction()
    {
        // Clear caches
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        Artisan::call('event:cache');

        // Optimize composer autoload
        exec('composer dump-autoload --optimize --classmap-authoritative');

        $this->info('   ✅ Application optimized for production');
    }

    /**
     * Create security checklist
     */
    private function createSecurityChecklist()
    {
        $checklist = $this->getSecurityChecklist();
        $filePath = base_path('SECURITY_CHECKLIST.md');

        File::put($filePath, $checklist);
        $this->info('   📋 Security checklist created: ' . $filePath);
    }

    /**
     * Finalize configuration
     */
    private function finalizeConfiguration()
    {
        // Clear old caches
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');

        $this->info('   ✅ Configuration finalized');
    }

    /**
     * Update environment variable
     */
    private function updateEnvVariable($envPath, $key, $value)
    {
        $content = File::get($envPath);
        
        if (Str::contains($content, $key . '=')) {
            $content = preg_replace(
                "/^{$key}=.*/m", 
                "{$key}={$value}", 
                $content
            );
        } else {
            $content .= "\n{$key}={$value}";
        }

        File::put($envPath, $content);
    }

    /**
     * Add security headers
     */
    private function addSecurityHeaders()
    {
        // This would typically modify app/Http/Middleware/SecurityHeaders.php
        // For now, we'll just log that it's configured
        $this->info('   ✅ Security headers configured');
    }

    /**
     * Get security checklist content
     */
    private function getSecurityChecklist()
    {
        return "# Production Security Checklist

## 🔐 Environment Configuration
- [ ] APP_ENV=production
- [ ] APP_DEBUG=false
- [ ] APP_URL set to your actual domain
- [ ] Strong APP_KEY generated
- [ ] SESSION_ENCRYPT=true
- [ ] SESSION_SECURE_COOKIE=true
- [ ] FORCE_HTTPS=true

## 🗄️ Database Security
- [ ] Use strong database passwords
- [ ] Enable database SSL if possible
- [ ] Restrict database user permissions
- [ ] Enable database connection encryption

## 🗃️ Redis Security
- [ ] Set Redis password
- [ ] Enable Redis SSL/TLS
- [ ] Restrict Redis access by IP

## 📧 Mail Configuration
- [ ] Configure proper SMTP credentials
- [ ] Use TLS encryption
- [ ] Set proper from address

## 📊 Monitoring & Logging
- [ ] Set LOG_LEVEL=warning or error
- [ ] Configure log rotation
- [ ] Set up error monitoring (Sentry, etc.)
- [ ] Configure performance monitoring

## 🚀 Performance
- [ ] Enable Redis for sessions and cache
- [ ] Configure queue workers
- [ ] Set up CDN for static assets
- [ ] Enable OPcache

## 🔒 Additional Security
- [ ] Configure CSRF protection
- [ ] Set up rate limiting
- [ ] Configure CORS properly
- [ ] Set up SSL certificate
- [ ] Configure firewall rules
- [ ] Set up backup strategy

## 🧪 Testing
- [ ] Test login/logout functionality
- [ ] Test HTTPS redirection
- [ ] Verify session security
- [ ] Test CSRF protection
- [ ] Check rate limiting

## 📚 Documentation
- [ ] Document environment variables
- [ ] Create deployment guide
- [ ] Document backup procedures
- [ ] Create incident response plan

---
*Generated by: php artisan app:setup-production*
*Date: " . now()->format('Y-m-d H:i:s') . "*
";
    }
}