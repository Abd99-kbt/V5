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
        Schema::create('sorting_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_processing_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_material_id')->constrained()->onDelete('cascade');

            // Original roll information
            $table->decimal('original_weight', 10, 2);
            $table->decimal('original_width', 8, 2);

            // Roll 1 (customer specifications)
            $table->decimal('roll1_weight', 10, 2);
            $table->decimal('roll1_width', 8, 2);
            $table->string('roll1_location')->nullable();

            // Roll 2 (remaining material)
            $table->decimal('roll2_weight', 10, 2);
            $table->decimal('roll2_width', 8, 2);
            $table->string('roll2_location')->nullable();

            // Waste
            $table->decimal('waste_weight', 10, 2)->default(0);
            $table->text('waste_reason')->nullable();

            // Sorting metadata
            $table->foreignId('sorted_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('sorted_at');
            $table->text('sorting_notes')->nullable();

            // Validation
            $table->boolean('weight_validated')->default(false);
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['order_processing_id', 'order_material_id']);
            $table->index('sorted_by');
            $table->index('validated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sorting_results');
    }
};
