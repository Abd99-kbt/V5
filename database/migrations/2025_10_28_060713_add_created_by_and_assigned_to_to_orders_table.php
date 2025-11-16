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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('current_stage', ['إنشاء', 'مراجعة', 'حجز_المواد', 'فرز', 'قص', 'تعبئة', 'فوترة', 'تسليم'])->default('إنشاء');
            $table->decimal('required_weight', 10, 2)->nullable();
            $table->decimal('required_length', 8, 2)->nullable();
            $table->decimal('required_width', 8, 2)->nullable();
            $table->integer('required_plates')->nullable();
            $table->enum('material_type', ['كرتون', 'ورق', 'بلاستيك', 'معدن', 'خشب', 'زجاج', 'آخر'])->nullable();
            $table->enum('delivery_method', ['استلام_ذاتي', 'توصيل', 'شحن'])->default('استلام_ذاتي');
            $table->decimal('estimated_price', 12, 2)->default(0);
            $table->decimal('final_price', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('specifications')->nullable();
            $table->boolean('is_urgent')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['assigned_to']);
            $table->dropColumn([
                'created_by',
                'assigned_to',
                'current_stage',
                'required_weight',
                'required_length',
                'required_width',
                'required_plates',
                'material_type',
                'delivery_method',
                'estimated_price',
                'final_price',
                'paid_amount',
                'remaining_amount',
                'discount',
                'submitted_at',
                'approved_at',
                'started_at',
                'completed_at',
                'specifications',
                'is_urgent',
            ]);
        });
    }
};
