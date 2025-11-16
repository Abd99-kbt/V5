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
        Schema::table('exports', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('exporter');
            $table->integer('total_rows')->default(0);
            $table->string('file_disk')->default('public');
            $table->timestamp('completed_at')->nullable();
            $table->string('file_name')->nullable();
            $table->integer('processed_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->json('column_mappings')->nullable();
            $table->json('options')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id',
                'exporter',
                'total_rows',
                'file_disk',
                'completed_at',
                'file_name',
                'processed_rows',
                'successful_rows',
                'failed_rows',
                'column_mappings',
                'options'
            ]);
        });
    }
};
