<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Contact information
            $table->string('phone')->nullable()->after('language');
            
            // Profile information
            $table->string('avatar')->nullable()->after('phone');
            
            // Account status and security
            $table->boolean('is_active')->default(true)->after('avatar');
            $table->boolean('is_email_verified')->default(false)->after('is_active');
            $table->timestamp('email_verified_at')->nullable()->after('is_email_verified');
            
            // Login tracking
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->integer('failed_login_attempts')->default(0)->after('last_login_ip');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            
            // MFA fields
            $table->boolean('mfa_enabled')->default(false)->after('locked_until');
            $table->text('mfa_secret')->nullable()->after('mfa_enabled');
            
            // OAuth fields
            $table->string('oauth_provider')->nullable()->after('mfa_secret');
            $table->string('oauth_id')->nullable()->after('oauth_provider');
            $table->text('oauth_token')->nullable()->after('oauth_id');
            $table->text('oauth_refresh_token')->nullable()->after('oauth_token');
            
            // Device and session tracking
            $table->string('device_fingerprint')->nullable()->after('oauth_refresh_token');
            
            // Security features
            $table->json('security_questions')->nullable()->after('device_fingerprint');
            $table->timestamp('password_changed_at')->nullable()->after('security_questions');
            
            // Account management
            $table->enum('account_type', ['admin', 'manager', 'operator', 'viewer', 'guest'])
                  ->default('viewer')->after('password_changed_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->after('account_type');
            $table->foreignId('updated_by')->nullable()->constrained('users')->after('created_by');
            
            // Add indexes for performance
            $table->index(['is_active', 'account_type']);
            $table->index(['username', 'is_active']);
            $table->index(['email', 'is_active']);
            $table->index(['last_login_at']);
            $table->index(['locked_until']);
            $table->index(['failed_login_attempts']);
            $table->index(['oauth_provider', 'oauth_id']);
            $table->index(['account_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'avatar',
                'is_active',
                'is_email_verified',
                'email_verified_at',
                'last_login_at',
                'last_login_ip',
                'failed_login_attempts',
                'locked_until',
                'mfa_enabled',
                'mfa_secret',
                'oauth_provider',
                'oauth_id',
                'oauth_token',
                'oauth_refresh_token',
                'device_fingerprint',
                'security_questions',
                'password_changed_at',
                'account_type',
                'created_by',
                'updated_by',
            ]);
        });
    }
};