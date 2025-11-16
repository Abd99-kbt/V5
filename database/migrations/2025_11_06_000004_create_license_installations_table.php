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
        Schema::create('license_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->onDelete('cascade');
            $table->string('hardware_id', 128)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('operating_system', 50)->nullable();
            $table->string('installation_path', 500)->nullable();
            $table->string('version', 20)->default('1.0.0');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('installed_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamp('deactivated_at')->nullable();
            $table->text('deactivation_reason')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['license_id', 'hardware_id']);
            $table->index(['license_id', 'is_active']);
            $table->index('installed_at');
            $table->index('last_seen_at');
            $table->index('deactivated_at');

            // Unique constraint to prevent duplicate installations
            $table->unique(['license_id', 'hardware_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_installations');
    }
};