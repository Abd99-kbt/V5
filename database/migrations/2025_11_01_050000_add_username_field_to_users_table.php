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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->unique()->nullable()->after('email')->comment('Arabic username for authentication');
                $table->index('username');
            }
        });

        // Update password reset tokens to also use username
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('password_reset_tokens', 'email') && !Schema::hasColumn('password_reset_tokens', 'username')) {
                $table->renameColumn('email', 'username');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['username']);
            $table->dropColumn('username');
        });

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->renameColumn('username', 'email');
        });
    }
};