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
        Schema::table('order_processings', function (Blueprint $table) {
            // Sorting-specific fields
            $table->boolean('sorting_approved')->default(false)->after('transfer_destination');
            $table->foreignId('sorting_approved_by')->nullable()->constrained('users')->onDelete('set null')->after('sorting_approved');
            $table->timestamp('sorting_approved_at')->nullable()->after('sorting_approved_by');
            $table->text('sorting_notes')->nullable()->after('sorting_approved_at');

            // Sorting results - roll 1 (customer order)
            $table->decimal('roll1_width', 8, 2)->nullable()->after('sorting_notes');
            $table->decimal('roll1_weight', 10, 2)->nullable()->after('roll1_width');
            $table->string('roll1_location')->nullable()->after('roll1_weight');

            // Sorting results - roll 2 (remaining)
            $table->decimal('roll2_width', 8, 2)->nullable()->after('roll1_location');
            $table->decimal('roll2_weight', 10, 2)->nullable()->after('roll2_width');
            $table->string('roll2_location')->nullable()->after('roll2_weight');

            // Waste tracking
            $table->decimal('sorting_waste_weight', 10, 2)->default(0)->after('roll2_location');

            // Transfer after sorting
            $table->enum('post_sorting_destination', ['cutting_warehouse', 'direct_delivery', 'other_warehouse'])->nullable()->after('sorting_waste_weight');
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null')->after('post_sorting_destination');
            $table->boolean('transfer_completed')->default(false)->after('destination_warehouse_id');
            $table->timestamp('transfer_completed_at')->nullable()->after('transfer_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_processings', function (Blueprint $table) {
            $table->dropForeign(['sorting_approved_by']);
            $table->dropForeign(['destination_warehouse_id']);
            $table->dropColumn([
                'sorting_approved',
                'sorting_approved_by',
                'sorting_approved_at',
                'sorting_notes',
                'roll1_width',
                'roll1_weight',
                'roll1_location',
                'roll2_width',
                'roll2_weight',
                'roll2_location',
                'sorting_waste_weight',
                'post_sorting_destination',
                'destination_warehouse_id',
                'transfer_completed',
                'transfer_completed_at'
            ]);
        });
    }
};
