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
        Schema::create('cutting_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('order_material_id')->constrained('order_materials')->onDelete('cascade');
            $table->foreignId('order_processing_id')->constrained('order_processings')->onDelete('cascade');

            // Cutting operation details
            $table->decimal('input_weight', 10, 2);
            $table->decimal('cut_weight', 10, 2);
            $table->decimal('waste_weight', 10, 2)->default(0);
            $table->decimal('remaining_weight', 10, 2)->default(0);

            // Cutting specifications
            $table->decimal('required_length', 8, 2)->nullable();
            $table->decimal('required_width', 8, 2)->nullable();
            $table->decimal('actual_cut_length', 8, 2)->nullable();
            $table->decimal('actual_cut_width', 8, 2)->nullable();

            // Material specifications
            $table->string('roll_number');
            $table->decimal('material_width', 8, 2)->nullable();
            $table->decimal('material_grammage', 8, 2)->nullable();
            $table->string('quality_grade')->nullable();
            $table->string('batch_number')->nullable();

            // Cutting details
            $table->integer('pieces_cut')->default(0);
            $table->text('cutting_notes')->nullable();
            $table->string('cutting_machine')->nullable();
            $table->string('operator_name')->nullable();

            // Status and approval
            $table->enum('status', ['pending', 'completed', 'approved', 'rejected'])->default('pending');
            $table->timestamp('cutting_completed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('performed_by')->constrained('users')->onDelete('cascade');

            // Quality control
            $table->boolean('quality_passed')->default(false);
            $table->text('quality_notes')->nullable();
            $table->json('quality_measurements')->nullable();

            // Transfer tracking
            $table->string('transfer_group_id')->nullable();
            $table->boolean('transfers_created')->default(false);

            $table->timestamps();

            // Indexes
            $table->index(['order_id', 'status']);
            $table->index(['order_processing_id']);
            $table->index(['transfer_group_id']);
            $table->index(['cutting_completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cutting_results');
    }
};
