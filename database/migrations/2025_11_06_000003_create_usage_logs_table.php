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
        Schema::create('usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('session_id', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('action', 255)->nullable();
            $table->string('resource', 500)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('url', 1000)->nullable();
            $table->json('request_data')->nullable();
            $table->integer('response_status')->nullable();
            $table->decimal('response_time', 8, 3)->nullable(); // milliseconds
            $table->bigInteger('memory_usage')->nullable(); // bytes
            $table->decimal('cpu_usage', 5, 2)->nullable(); // percentage
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['license_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index('created_at');
            $table->index('response_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_logs');
    }
};