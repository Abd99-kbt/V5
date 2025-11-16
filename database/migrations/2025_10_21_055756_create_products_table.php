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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('sku')->unique();
            $table->string('barcode')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('image')->nullable();

            // Enhanced product attributes
            $table->enum('type', ['roll', 'digma', 'bale', 'sheet'])->default('roll');
            $table->integer('grammage')->nullable(); // weight per square meter
            $table->string('quality')->nullable(); // standard, stock, premium
            $table->string('roll_number')->nullable();
            $table->string('source')->nullable(); // China, Holland, Spain, Internal, etc.
            $table->decimal('length', 10, 2)->nullable(); // in cm
            $table->decimal('width', 10, 2)->nullable(); // in cm
            $table->decimal('thickness', 8, 3)->nullable(); // in mm

            // Pricing
            $table->decimal('purchase_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->decimal('wholesale_price', 10, 2)->nullable();
            $table->decimal('material_cost_per_ton', 10, 2)->nullable();

            // Inventory management
            $table->integer('min_stock_level')->default(0);
            $table->integer('max_stock_level')->nullable();
            $table->string('unit')->default('kg');
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('reserved_weight', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('track_inventory')->default(true);

            // Relationships
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
