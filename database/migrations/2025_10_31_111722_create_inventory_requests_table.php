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
        Schema::create('inventory_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weight_transfer_id')->constrained('weight_transfers')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('request_type'); // 'source_check', 'destination_check', 'both'
            $table->string('status')->default('pending'); // 'pending', 'completed', 'cancelled'
            $table->text('request_notes')->nullable();
            $table->json('inventory_data')->nullable(); // Store inventory counts/details
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index(['weight_transfer_id']);
            $table->index(['requested_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_requests');
    }
};
