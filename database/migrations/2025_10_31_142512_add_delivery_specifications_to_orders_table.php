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
        Schema::table('orders', function (Blueprint $table) {
            // Delivery specifications fields
            $table->decimal('delivery_width', 10, 2)->nullable()->after('customer_address');
            $table->decimal('delivery_length', 10, 2)->nullable()->after('delivery_width');
            $table->decimal('delivery_thickness', 10, 2)->nullable()->after('delivery_length');
            $table->decimal('delivery_grammage', 10, 2)->nullable()->after('delivery_thickness');
            $table->string('delivery_quality')->nullable()->after('delivery_grammage');
            $table->integer('delivery_quantity')->nullable()->after('delivery_quality');
            $table->decimal('delivery_weight', 10, 2)->nullable()->after('delivery_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_width',
                'delivery_length',
                'delivery_thickness',
                'delivery_grammage',
                'delivery_quality',
                'delivery_quantity',
                'delivery_weight',
            ]);
        });
    }
};
