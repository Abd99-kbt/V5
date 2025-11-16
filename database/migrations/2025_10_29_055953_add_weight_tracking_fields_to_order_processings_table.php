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
            $table->decimal('weight_received', 10, 2)->default(0)->after('stage_metadata');
            $table->decimal('weight_transferred', 10, 2)->default(0)->after('weight_received');
            $table->decimal('weight_balance', 10, 2)->default(0)->after('weight_transferred');
            $table->boolean('transfer_approved')->default(false)->after('weight_balance');
            $table->foreignId('transfer_approved_by')->nullable()->constrained('users')->onDelete('set null')->after('transfer_approved');
            $table->timestamp('transfer_approved_at')->nullable()->after('transfer_approved_by');
            $table->text('transfer_notes')->nullable()->after('transfer_approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_processings', function (Blueprint $table) {
            $table->dropForeign(['transfer_approved_by']);
            $table->dropColumn([
                'weight_received',
                'weight_transferred',
                'weight_balance',
                'transfer_approved',
                'transfer_approved_by',
                'transfer_approved_at',
                'transfer_notes'
            ]);
        });
    }
};
