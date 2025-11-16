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
        Schema::table('order_items', function (Blueprint $table) {
            // Additional fields for business requirements
            $table->unsignedBigInteger('warehouse_stock_id')->nullable()->after('product_id');
            $table->json('cutting_specifications')->nullable()->after('notes');
            $table->decimal('weight', 10, 2)->nullable()->after('total_price');
            $table->decimal('required_weight', 10, 2)->nullable()->after('weight');
            $table->decimal('delivered_weight', 10, 2)->nullable()->after('required_weight');
            $table->decimal('cutting_fees', 10, 2)->nullable()->after('delivered_weight');
            
            // Foreign key constraint
            $table->foreign('warehouse_stock_id')->references('id')->on('stocks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_stock_id']);
            $table->dropColumn([
                'warehouse_stock_id',
                'cutting_specifications',
                'weight',
                'required_weight',
                'delivered_weight',
                'cutting_fees'
            ]);
        });
    }
};
