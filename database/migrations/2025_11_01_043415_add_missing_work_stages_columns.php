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
        Schema::table('work_stages', function (Blueprint $table) {
            if (!Schema::hasColumn('work_stages', 'color')) {
                $table->string('color')->default('gray')->after('order');
            }
            if (!Schema::hasColumn('work_stages', 'icon')) {
                $table->string('icon')->default('heroicon-o-circle')->after('color');
            }
            if (!Schema::hasColumn('work_stages', 'can_skip')) {
                $table->boolean('can_skip')->default(false)->after('icon');
            }
            if (!Schema::hasColumn('work_stages', 'requires_role')) {
                $table->string('requires_role')->nullable()->after('can_skip');
            }
            if (!Schema::hasColumn('work_stages', 'estimated_duration')) {
                $table->integer('estimated_duration')->default(30)->after('requires_role');
            }
            if (!Schema::hasColumn('work_stages', 'stage_group')) {
                $table->string('stage_group')->default('general')->after('estimated_duration');
            }
            if (!Schema::hasColumn('work_stages', 'is_mandatory')) {
                $table->boolean('is_mandatory')->default(true)->after('stage_group');
            }
            if (!Schema::hasColumn('work_stages', 'skip_conditions')) {
                $table->json('skip_conditions')->nullable()->after('is_mandatory');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_stages', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('work_stages', 'color')) {
                $columnsToDrop[] = 'color';
            }
            if (Schema::hasColumn('work_stages', 'icon')) {
                $columnsToDrop[] = 'icon';
            }
            if (Schema::hasColumn('work_stages', 'can_skip')) {
                $columnsToDrop[] = 'can_skip';
            }
            if (Schema::hasColumn('work_stages', 'requires_role')) {
                $columnsToDrop[] = 'requires_role';
            }
            if (Schema::hasColumn('work_stages', 'estimated_duration')) {
                $columnsToDrop[] = 'estimated_duration';
            }
            if (Schema::hasColumn('work_stages', 'stage_group')) {
                $columnsToDrop[] = 'stage_group';
            }
            if (Schema::hasColumn('work_stages', 'is_mandatory')) {
                $columnsToDrop[] = 'is_mandatory';
            }
            if (Schema::hasColumn('work_stages', 'skip_conditions')) {
                $columnsToDrop[] = 'skip_conditions';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};