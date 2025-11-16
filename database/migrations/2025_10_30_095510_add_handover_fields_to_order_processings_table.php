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
            $table->enum('handover_status', ['not_required', 'pending', 'in_progress', 'completed'])->default('not_required');
            $table->foreignId('handover_from')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('handover_to')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('handover_requested_at')->nullable();
            $table->timestamp('handover_completed_at')->nullable();
            $table->text('handover_notes')->nullable();
            $table->boolean('mandatory_handover')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_processings', function (Blueprint $table) {
            $table->dropForeign(['handover_from']);
            $table->dropForeign(['handover_to']);
            $table->dropColumn(['handover_status', 'handover_from', 'handover_to', 'handover_requested_at', 'handover_completed_at', 'handover_notes', 'mandatory_handover']);
        });
    }
};
