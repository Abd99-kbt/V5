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
        // فهارس للأداء في جدول orders - فقط الفهارس الجديدة
        // تم إزالة الفهارس المكررة

        // فهارس للأداء في جدول order_processings
        // تم إزالة الفهارس المكررة - سنركز على جداول أخرى

        // فهارس للأداء في جدول products
        // تم إزالة الفهارس المكررة

        // فهارس للأداء في جدول stocks
        // تم إزالة الفهارس المكررة

        // فهارس للأداء في جدول customers
        // تم إزالة الفهارس المكررة

        // فهارس للأداء في جداول أخرى
        // تم إزالة الجداول غير الموجودة (material_movements, waste_tracking)

        // فهارس للأداء في جدول invoices
        // تم إزالة الفهارس التي تحتوي على أعمدة غير موجودة
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // إزالة الفهارس عند التراجع
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_stage_date_idx');
            $table->dropIndex('orders_customer_status_idx');
            $table->dropIndex('orders_assigned_status_idx');
            $table->dropIndex('orders_delivery_status_idx');
            $table->dropIndex('orders_material_status_idx');
            $table->dropIndex('orders_urgent_status_idx');
        });

        Schema::table('order_processings', function (Blueprint $table) {
            $table->dropIndex('processings_order_stage_idx');
            $table->dropIndex('processings_assigned_status_idx');
            $table->dropIndex('processings_status_updated_idx');
            $table->dropIndex('processings_stage_status_idx');
            $table->dropIndex('processings_time_range_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_type_quality_active_idx');
            $table->dropIndex('products_warehouse_active_idx');
            $table->dropIndex('products_cost_active_idx');
            $table->dropIndex('products_name_active_idx');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex('stocks_product_warehouse_idx');
            $table->dropIndex('stocks_quantity_reserved_idx');
            $table->dropIndex('stocks_active_warehouse_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_active_created_idx');
            $table->dropIndex('customers_rep_active_idx');
        });

        Schema::table('material_movements', function (Blueprint $table) {
            $table->dropIndex('movements_material_date_idx');
            $table->dropIndex('movements_order_type_idx');
            $table->dropIndex('movements_warehouses_idx');
        });

        Schema::table('waste_tracking', function (Blueprint $table) {
            $table->dropIndex('waste_order_date_idx');
            $table->dropIndex('waste_type_reason_idx');
            $table->dropIndex('waste_reporter_date_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_customer_status_date_idx');
            $table->dropIndex('invoices_due_status_idx');
            $table->dropIndex('invoices_amount_status_idx');
        });
    }
};