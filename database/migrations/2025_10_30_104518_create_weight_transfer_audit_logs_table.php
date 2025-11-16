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
        Schema::create('weight_transfer_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_processing_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('weight_received', 10, 2)->default(0);
            $table->decimal('weight_transferred', 10, 2)->default(0);
            $table->enum('transfer_destination', ['sorting', 'cutting', 'final_delivery'])->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weight_transfer_audit_logs');
    }
};
