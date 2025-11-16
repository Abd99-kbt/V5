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
            // Add fields for grouped transfers after sorting
            $table->string('transfer_group_id')->nullable()->after('transfer_metadata');
            $table->enum('transfer_category', ['sorted_material', 'waste', 'remaining_roll'])->nullable()->after('transfer_group_id');
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null')->after('transfer_category');
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null')->after('source_warehouse_id');
            $table->boolean('requires_sequential_approval')->default(true)->after('destination_warehouse_id');
            $table->integer('current_approval_level')->default(1)->after('requires_sequential_approval');

            // Add indexes
            $table->index(['transfer_group_id', 'transfer_category'], 'wt_transfer_group_category_idx');
            $table->index(['source_warehouse_id', 'destination_warehouse_id'], 'wt_source_dest_warehouse_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weight_transfers', function (Blueprint $table) {
            $table->dropForeign(['source_warehouse_id']);
            $table->dropForeign(['destination_warehouse_id']);
            $table->dropIndex('wt_transfer_group_category_idx');
            $table->dropIndex('wt_source_dest_warehouse_idx');
            $table->dropColumn([
                'transfer_group_id',
                'transfer_category',
                'source_warehouse_id',
                'destination_warehouse_id',
                'requires_sequential_approval',
                'current_approval_level'
            ]);
        });
    }
};
