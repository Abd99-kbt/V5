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
        Schema::create('mfa_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('device_name');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('fingerprint')->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'expires_at']);
            $table->index('fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mfa_trusted_devices');
    }
};