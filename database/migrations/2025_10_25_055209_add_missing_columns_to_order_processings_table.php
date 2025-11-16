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
        // Columns already exist from the original migration, skip adding them again
        // This migration is redundant and should be removed or modified
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_processings', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['work_stage_id']);
            $table->dropForeign(['assigned_to']);
            $table->dropColumn([
                'order_id',
                'work_stage_id',
                'status',
                'started_at',
                'completed_at',
                'notes',
                'assigned_to',
                'priority',
            ]);
        });
    }
};
