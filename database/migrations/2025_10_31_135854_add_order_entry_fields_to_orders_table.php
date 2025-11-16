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
            // Order entry workflow fields
            $table->json('material_requirements')->nullable()->after('specifications');
            $table->decimal('estimated_material_cost', 10, 2)->default(0)->after('estimated_price');
            $table->decimal('labor_cost_estimate', 10, 2)->default(0)->after('estimated_material_cost');
            $table->decimal('overhead_cost_estimate', 10, 2)->default(0)->after('labor_cost_estimate');
            $table->decimal('profit_margin_percentage', 5, 2)->default(0)->after('overhead_cost_estimate');
            $table->decimal('profit_margin_amount', 10, 2)->default(0)->after('profit_margin_percentage');
            $table->json('pricing_breakdown')->nullable()->after('profit_margin_amount');
            $table->boolean('auto_material_selection')->default(true)->after('pricing_breakdown');
            $table->json('selected_materials')->nullable()->after('auto_material_selection');
            $table->timestamp('materials_selected_at')->nullable()->after('selected_materials');
            $table->unsignedBigInteger('materials_selected_by')->nullable()->after('materials_selected_at');
            $table->boolean('pricing_calculated')->default(false)->after('materials_selected_by');
            $table->timestamp('pricing_calculated_at')->nullable()->after('pricing_calculated');
            $table->unsignedBigInteger('pricing_calculated_by')->nullable()->after('pricing_calculated_at');

            // Foreign key constraints
            $table->foreign('materials_selected_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('pricing_calculated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['materials_selected_by']);
            $table->dropForeign(['pricing_calculated_by']);

            // Drop columns
            $table->dropColumn([
                'material_requirements',
                'estimated_material_cost',
                'labor_cost_estimate',
                'overhead_cost_estimate',
                'profit_margin_percentage',
                'profit_margin_amount',
                'pricing_breakdown',
                'auto_material_selection',
                'selected_materials',
                'materials_selected_at',
                'materials_selected_by',
                'pricing_calculated',
                'pricing_calculated_at',
                'pricing_calculated_by',
            ]);
        });
    }
};
