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
            // Pricing fields
            $table->decimal('price_per_ton', 10, 2)->default(0)->after('remaining_amount');
            $table->decimal('cutting_fees', 10, 2)->default(0)->after('price_per_ton');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['price_per_ton', 'cutting_fees']);
        });
    }
};
