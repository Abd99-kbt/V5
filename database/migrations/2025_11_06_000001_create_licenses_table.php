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
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key', 100)->unique();
            $table->string('customer_email');
            $table->string('customer_name');
            $table->string('product_name')->default('Advanced Order Processing System');
            $table->enum('license_type', ['trial', 'basic', 'professional', 'enterprise'])->default('professional');
            $table->integer('max_users')->default(10);
            $table->integer('max_installations')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('activation_count')->default(0);
            $table->timestamp('last_activation_at')->nullable();
            $table->json('allowed_domains')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->json('features')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->text('deactivation_reason')->nullable();
            $table->timestamps();

            $table->index(['license_key', 'is_active']);
            $table->index(['customer_email']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};