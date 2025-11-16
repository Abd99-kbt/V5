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
        Schema::table('weight_transfers', function (Blueprint $table) {
            // Material specifications tracking
            $table->string('roll_number')->nullable()->after('transfer_metadata');
            $table->decimal('material_width', 8, 2)->nullable()->after('roll_number');
            $table->decimal('material_length', 8, 2)->nullable()->after('material_width');
            $table->decimal('material_grammage', 8, 2)->nullable()->after('material_length');
            $table->string('quality_grade')->nullable()->after('material_grammage');
            $table->string('batch_number')->nullable()->after('quality_grade');

            $table->index(['roll_number']);
            $table->index(['quality_grade']);
            $table->index(['material_width', 'material_grammage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weight_transfers', function (Blueprint $table) {
            $table->dropColumn([
                'roll_number',
                'material_width',
                'material_length',
                'material_grammage',
                'quality_grade',
                'batch_number',
            ]);
        });
    }
};
