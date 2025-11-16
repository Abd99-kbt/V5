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
        Schema::table('weight_transfer_approvals', function (Blueprint $table) {
            // Add warehouse-specific approval fields
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null')->after('approver_id');
            $table->enum('approval_level', ['cutting_warehouse_manager', 'main_warehouse_manager', 'auto_approved'])->default('cutting_warehouse_manager')->after('warehouse_id');
            $table->integer('approval_sequence')->default(1)->after('approval_level');
            $table->boolean('is_final_approval')->default(false)->after('approval_sequence');

            // Add indexes for better performance
            $table->index(['warehouse_id', 'approval_level'], 'wta_warehouse_level_idx');
            $table->index(['weight_transfer_id', 'approval_sequence'], 'wta_transfer_sequence_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weight_transfer_approvals', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropIndex('wta_warehouse_level_idx');
            $table->dropIndex('wta_transfer_sequence_idx');
            $table->dropColumn([
                'warehouse_id',
                'approval_level',
                'approval_sequence',
                'is_final_approval'
            ]);
        });
    }
};
