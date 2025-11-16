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
        // Columns already exist from the original migration, skip adding them again
        // This migration is redundant and should be removed or modified
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_stages', function (Blueprint $table) {
            $table->dropColumn([
                'name_en',
                'name_ar',
                'description_en',
                'description_ar',
                'order',
                'is_active',
            ]);
        });
    }
};
