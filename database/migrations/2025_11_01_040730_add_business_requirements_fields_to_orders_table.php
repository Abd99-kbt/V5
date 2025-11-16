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
            // Additional fields for business requirements - only add if they don't exist
            if (!Schema::hasColumn('orders', 'delivery_location')) {
                $table->string('delivery_location')->nullable()->after('customer_address');
            }
            if (!Schema::hasColumn('orders', 'number_of_plates')) {
                $table->integer('number_of_plates')->nullable()->after('delivery_weight');
            }
            if (!Schema::hasColumn('orders', 'cutting_fees_per_ton')) {
                $table->decimal('cutting_fees_per_ton', 10, 2)->nullable()->after('cutting_fees');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop only the columns that were added by this migration
            $columnsToDrop = [];
            if (Schema::hasColumn('orders', 'delivery_location')) {
                $columnsToDrop[] = 'delivery_location';
            }
            if (Schema::hasColumn('orders', 'number_of_plates')) {
                $columnsToDrop[] = 'number_of_plates';
            }
            if (Schema::hasColumn('orders', 'cutting_fees_per_ton')) {
                $columnsToDrop[] = 'cutting_fees_per_ton';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
