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
        Schema::create('order_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('stage_name');
            $table->integer('stage_order');
            $table->string('status')->default('معلق'); // معلق, قيد_التنفيذ, مكتمل, مرفوض
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('weight_input', 10, 2)->nullable();
            $table->decimal('weight_output', 10, 2)->nullable();
            $table->decimal('waste_weight', 10, 2)->nullable();
            $table->string('waste_reason')->nullable();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->boolean('requires_approval')->default(false);
            $table->string('approval_status')->nullable(); // معتمد, مرفوض
            $table->timestamps();

            $table->index(['order_id', 'stage_order']);
            $table->index(['assigned_to', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_stages');
    }
};
