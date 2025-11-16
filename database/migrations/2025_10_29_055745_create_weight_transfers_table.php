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
        Schema::create('weight_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_material_id')->constrained()->onDelete('cascade');
            $table->string('from_stage', 100);
            $table->string('to_stage', 100);
            $table->decimal('weight_transferred', 10, 2);
            $table->enum('transfer_type', ['initial', 'stage_transfer', 'return', 'waste'])->default('stage_transfer');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->json('transfer_metadata')->nullable(); // additional transfer details
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['from_stage', 'to_stage']);
            $table->index(['requested_by', 'approved_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weight_transfers');
    }
};
