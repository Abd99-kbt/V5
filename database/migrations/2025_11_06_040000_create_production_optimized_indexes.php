<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for production-optimized indexing.
     * This migration includes comprehensive indexes for high-traffic scenarios.
     */
    public function up(): void
    {
        // ===============================================
        // PRODUCTION OPTIMIZED INDEXES
        // ===============================================

        // Orders table indexes - critical for order management
        // Schema::table('orders', function (Blueprint $table) {
        //     // Search optimization indexes
        //     $table->index(['user_id', 'created_at'], 'orders_user_date_idx');
        //     $table->index(['status', 'created_at'], 'orders_status_date_idx');
        //     $table->index(['customer_id'], 'orders_customer_idx');
        // });

        // Order Processings table indexes - critical for workflow
        // Schema::table('order_processings', function (Blueprint $table) {
        //     // Workflow optimization indexes
        //     $table->index(['order_id', 'status'], 'processings_order_status_idx');
        //     $table->index(['assigned_to', 'status'], 'processings_assigned_status_idx');
        //     $table->index(['status', 'updated_at'], 'processings_status_updated_idx');
        //     $table->index(['completed_at'], 'processings_completed_at_idx');

        //     // Performance tracking indexes
        //     $table->index(['transfer_destination', 'status'], 'processings_transfer_status_idx');
        //     $table->index(['sorting_results_count'], 'processings_sorting_count_idx');

        //     // Time-based performance indexes
        //     $table->index(['stage_started_at'], 'processings_time_tracking_idx');
        //     $table->index(['created_at', 'assigned_to'], 'processings_assignment_tracking_idx');
        // });

        // Order Items table indexes
        // Schema::table('order_items', function (Blueprint $table) {
        //     $table->index(['order_id', 'created_at'], 'order_items_order_date_idx');
        //     $table->index(['product_id', 'created_at'], 'order_items_product_date_idx');
        // });

        // Products table indexes - essential for inventory
        // Schema::table('products', function (Blueprint $table) {
        //     // Inventory management indexes
        //     $table->index(['category_id'], 'products_category_idx');
        //     $table->index(['supplier_id'], 'products_supplier_idx');

        //     // Search optimization indexes
        //     $table->index(['name'], 'products_name_idx');
        //     $table->index(['created_at'], 'products_created_idx');
        // });

        // Stocks table indexes - critical for inventory tracking
        // Schema::table('stocks', function (Blueprint $table) {
        //     // Real-time inventory tracking
        //     $table->index(['product_id', 'warehouse_id'], 'stocks_product_warehouse_idx');
        //     $table->index(['quantity'], 'stocks_quantity_idx');
        //     $table->index(['warehouse_id'], 'stocks_warehouse_idx');
        // });

        // Customers table indexes
        // Schema::table('customers', function (Blueprint $table) {
        //     // Customer management indexes
        //     $table->index(['created_at'], 'customers_created_idx');

        //     // Search optimization indexes
        //     $table->index(['name'], 'customers_name_idx');
        //     $table->index(['phone'], 'customers_phone_idx');
        // });

        // Invoices table indexes - critical for billing
        // Schema::table('invoices', function (Blueprint $table) {
        //     // Billing optimization indexes
        //     $table->index(['customer_id', 'created_at'], 'invoices_customer_date_idx');
        //     $table->index(['due_date'], 'invoices_due_date_idx');
        //     $table->index(['created_at'], 'invoices_created_date_idx');

        //     // Payment tracking indexes
        //     $table->index(['paid_at'], 'invoices_paid_at_idx');

        //     // Invoice number optimization
        //     $table->index(['invoice_number'])->unique('invoices_number_unique_idx');
        // });

        // Users table indexes
        // Schema::table('users', function (Blueprint $table) {
        //     // Authentication optimization indexes
        //     $table->index(['username'], 'users_username_idx');
        //     $table->index(['email'], 'users_email_idx');

        //     // Role and permission optimization
        //     $table->index(['created_at'], 'users_created_at_idx');
        // });

        // Warehouses table indexes
        // Schema::table('warehouses', function (Blueprint $table) {
        //     $table->index(['type'], 'warehouses_type_idx');
        // });

        // Stock Alerts table indexes
        // Schema::table('stock_alerts', function (Blueprint $table) {
        //     $table->index(['product_id', 'warehouse_id'], 'alerts_product_warehouse_idx');
        //     $table->index(['created_at'], 'alerts_created_idx');
        // });

        // Transfers table indexes
        // Schema::table('transfers', function (Blueprint $table) {
        //     $table->index(['created_at', 'status'], 'transfers_created_status_idx');
        //     $table->index(['product_id', 'status'], 'transfers_product_status_idx');
        // });

        // Work Stages table indexes
        // Schema::table('work_stages', function (Blueprint $table) {
        //     $table->index(['order'], 'work_stages_order_idx');
        // });

        // Weight Transfers table indexes
        // Schema::table('weight_transfers', function (Blueprint $table) {
        //     $table->index(['transfer_group_id', 'status'], 'weight_transfers_group_status_idx');
        //     $table->index(['material_specification'], 'weight_transfers_material_spec_idx');
        //     $table->index(['created_at', 'status'], 'weight_transfers_created_status_idx');
        // });

        // Sorting Results table indexes
        // Schema::table('sorting_results', function (Blueprint $table) {
        //     $table->index(['order_processing_id', 'created_at'], 'sorting_results_processing_date_idx');
        // });

        // Warehouse Employee Assignments table indexes
        Schema::table('warehouse_employee_assignments', function (Blueprint $table) {
            $table->index(['warehouse_id', 'assigned_at'], 'assignments_warehouse_assigned_idx');
        });

        // ===============================================
        // FULL-TEXT SEARCH INDEXES (MySQL 8.0+ / PostgreSQL)
        // ===============================================

        // Add full-text indexes for search functionality
        try {
            DB::statement('ALTER TABLE orders ADD FULLTEXT INDEX orders_search_idx (order_number, notes)');
            DB::statement('ALTER TABLE products ADD FULLTEXT INDEX products_search_idx (name, description)');
            DB::statement('ALTER TABLE customers ADD FULLTEXT INDEX customers_search_idx (name, address)');
        } catch (\Exception $e) {
            // Skip full-text indexes if not supported
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all production indexes in reverse order
        
        Schema::table('warehouse_employee_assignments', function (Blueprint $table) {
            $table->dropIndex('assignments_warehouse_assigned_idx');
        });

        // Schema::table('sorting_results', function (Blueprint $table) {
        //     $table->dropIndex('sorting_results_processing_date_idx');
        // });

        // Schema::table('weight_transfers', function (Blueprint $table) {
        //     $table->dropIndex('weight_transfers_group_status_idx');
        //     $table->dropIndex('weight_transfers_material_spec_idx');
        //     $table->dropIndex('weight_transfers_created_status_idx');
        // });

        // Schema::table('work_stages', function (Blueprint $table) {
        //     $table->dropIndex('work_stages_order_idx');
        // });

        // Schema::table('transfers', function (Blueprint $table) {
        //     $table->dropIndex('transfers_created_status_idx');
        //     $table->dropIndex('transfers_product_status_idx');
        // });

        // Schema::table('stock_alerts', function (Blueprint $table) {
        //     $table->dropIndex('alerts_product_warehouse_idx');
        //     $table->dropIndex('alerts_created_idx');
        // });

        // Schema::table('warehouses', function (Blueprint $table) {
        //     $table->dropIndex('warehouses_type_idx');
        // });

        // Schema::table('users', function (Blueprint $table) {
        //     $table->dropIndex('users_username_idx');
        //     $table->dropIndex('users_email_idx');
        //     $table->dropIndex('users_created_at_idx');
        // });

        // Schema::table('invoices', function (Blueprint $table) {
        //     $table->dropIndex('invoices_customer_date_idx');
        //     $table->dropIndex('invoices_due_date_idx');
        //     $table->dropIndex('invoices_created_date_idx');
        //     $table->dropIndex('invoices_paid_at_idx');
        //     $table->dropIndex('invoices_number_unique_idx');
        // });

        // Schema::table('customers', function (Blueprint $table) {
        //     $table->dropIndex('customers_created_idx');
        //     $table->dropIndex('customers_name_idx');
        //     $table->dropIndex('customers_phone_idx');
        // });

        // Schema::table('stocks', function (Blueprint $table) {
        //     $table->dropIndex('stocks_product_warehouse_idx');
        //     $table->dropIndex('stocks_quantity_idx');
        //     $table->dropIndex('stocks_warehouse_idx');
        // });

        // Schema::table('products', function (Blueprint $table) {
        //     $table->dropIndex('products_category_idx');
        //     $table->dropIndex('products_supplier_idx');
        //     $table->dropIndex('products_name_idx');
        //     $table->dropIndex('products_created_idx');
        // });

        // Schema::table('order_items', function (Blueprint $table) {
        //     $table->dropIndex('order_items_order_date_idx');
        //     $table->dropIndex('order_items_product_date_idx');
        // });

        // Schema::table('order_processings', function (Blueprint $table) {
        //     $table->dropIndex('processings_order_status_idx');
        //     $table->dropIndex('processings_assigned_status_idx');
        //     $table->dropIndex('processings_status_updated_idx');
        //     $table->dropIndex('processings_completed_at_idx');
        //     $table->dropIndex('processings_transfer_status_idx');
        //     $table->dropIndex('processings_sorting_count_idx');
        //     $table->dropIndex('processings_time_tracking_idx');
        //     $table->dropIndex('processings_assignment_tracking_idx');
        // });

        // Schema::table('orders', function (Blueprint $table) {
        //     $table->dropIndex('orders_user_date_idx');
        //     $table->dropIndex('orders_status_date_idx');
        //     $table->dropIndex('orders_customer_idx');
        // });

        // Drop full-text indexes
        try {
            DB::statement('ALTER TABLE orders DROP INDEX orders_search_idx');
            DB::statement('ALTER TABLE products DROP INDEX products_search_idx');
            DB::statement('ALTER TABLE customers DROP INDEX customers_search_idx');
        } catch (\Exception $e) {
            // Skip if full-text indexes don't exist
        }
    }
};