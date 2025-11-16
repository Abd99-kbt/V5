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
        Schema::create('weight_transfer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weight_transfer_id')->constrained()->onDelete('cascade');
            $table->foreignId('approver_id')->constrained('users')->onDelete('cascade');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('approval_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('approval_metadata')->nullable();
            $table->timestamps();

            $table->index(['weight_transfer_id', 'approval_status'], 'wta_wt_id_status_idx');
            $table->index(['approver_id', 'approval_status'], 'wta_approver_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weight_transfer_approvals');
    }
};
