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
        Schema::create('order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('material_id')->constrained('products')->onDelete('cascade');
            $table->decimal('requested_weight', 10, 2);
            $table->decimal('extracted_weight', 10, 2)->default(0);
            $table->decimal('sorted_weight', 10, 2)->default(0);
            $table->decimal('cut_weight', 10, 2)->default(0);
            $table->decimal('delivered_weight', 10, 2)->default(0);
            $table->decimal('returned_weight', 10, 2)->default(0);
            $table->decimal('sorting_waste_weight', 10, 2)->default(0);
            $table->decimal('cutting_waste_weight', 10, 2)->default(0);
            $table->decimal('total_waste_weight', 10, 2)->default(0);
            $table->enum('status', ['مطلوب', 'مستخرج', 'مفرز', 'مقصوص', 'مُسلم', 'مُعاد'])->default('مطلوب');
            $table->timestamp('extracted_at')->nullable();
            $table->timestamp('sorted_at')->nullable();
            $table->timestamp('cut_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('sorting_waste_reason')->nullable();
            $table->text('cutting_waste_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('specifications')->nullable(); // width, length, grammage, etc.
            $table->timestamps();

            $table->index(['order_id', 'material_id']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_materials');
    }
};
