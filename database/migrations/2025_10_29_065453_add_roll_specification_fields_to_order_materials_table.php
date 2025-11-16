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
        Schema::table('order_materials', function (Blueprint $table) {
            // Roll specifications
            $table->string('roll_number')->nullable()->after('specifications');
            $table->decimal('required_width', 8, 2)->nullable()->after('roll_number');
            $table->decimal('required_length', 8, 2)->nullable()->after('required_width');
            $table->decimal('required_grammage', 8, 2)->nullable()->after('required_length');
            $table->string('quality_grade')->nullable()->after('required_grammage');
            $table->decimal('actual_width', 8, 2)->nullable()->after('quality_grade');
            $table->decimal('actual_length', 8, 2)->nullable()->after('actual_width');
            $table->decimal('actual_grammage', 8, 2)->nullable()->after('actual_length');
            $table->foreignId('roll_source_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null')->after('actual_grammage');
            $table->foreignId('roll_source_stock_id')->nullable()->constrained('stocks')->onDelete('set null')->after('roll_source_warehouse_id');

            $table->index(['roll_number']);
            $table->index(['quality_grade']);
            $table->index(['required_width', 'required_grammage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_materials', function (Blueprint $table) {
            $table->dropForeign(['roll_source_warehouse_id']);
            $table->dropForeign(['roll_source_stock_id']);
            $table->dropColumn([
                'roll_number',
                'required_width',
                'required_length',
                'required_grammage',
                'quality_grade',
                'actual_width',
                'actual_length',
                'actual_grammage',
                'roll_source_warehouse_id',
                'roll_source_stock_id',
            ]);
        });
    }
};
