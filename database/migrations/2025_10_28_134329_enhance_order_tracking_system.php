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
        // Enhance order_processings table with visual and flexible progression features
        Schema::table('order_processings', function (Blueprint $table) {
            // Check if columns exist before adding them
            if (!Schema::hasColumn('order_processings', 'stage_color')) {
                $table->string('stage_color', 20)->default('gray')->after('status');
            }
            if (!Schema::hasColumn('order_processings', 'can_skip')) {
                $table->boolean('can_skip')->default(false)->after('stage_color');
            }
            if (!Schema::hasColumn('order_processings', 'skip_reason')) {
                $table->text('skip_reason')->nullable()->after('can_skip');
            }
            if (!Schema::hasColumn('order_processings', 'skipped_at')) {
                $table->timestamp('skipped_at')->nullable()->after('skip_reason');
            }
            if (!Schema::hasColumn('order_processings', 'skipped_by')) {
                $table->foreignId('skipped_by')->nullable()->constrained('users')->onDelete('set null')->after('skipped_at');
            }
            if (!Schema::hasColumn('order_processings', 'visual_priority')) {
                $table->integer('visual_priority')->default(1)->after('priority');
            }
            if (!Schema::hasColumn('order_processings', 'estimated_duration')) {
                $table->integer('estimated_duration')->nullable()->after('visual_priority'); // minutes
            }
            if (!Schema::hasColumn('order_processings', 'actual_duration')) {
                $table->integer('actual_duration')->nullable()->after('estimated_duration'); // minutes
            }
            if (!Schema::hasColumn('order_processings', 'stage_metadata')) {
                $table->json('stage_metadata')->nullable()->after('actual_duration');
            }
        });

        // Enhance work_stages table with visual and role-based features
        Schema::table('work_stages', function (Blueprint $table) {
            if (!Schema::hasColumn('work_stages', 'color')) {
                $table->string('color', 20)->default('gray')->after('order');
            }
            if (!Schema::hasColumn('work_stages', 'icon')) {
                $table->string('icon', 50)->nullable()->after('color');
            }
            if (!Schema::hasColumn('work_stages', 'can_skip')) {
                $table->boolean('can_skip')->default(false)->after('icon');
            }
            if (!Schema::hasColumn('work_stages', 'requires_role')) {
                $table->string('requires_role', 50)->nullable()->after('can_skip');
            }
            if (!Schema::hasColumn('work_stages', 'estimated_duration')) {
                $table->integer('estimated_duration')->nullable()->after('requires_role'); // minutes
            }
            if (!Schema::hasColumn('work_stages', 'stage_group')) {
                $table->string('stage_group', 50)->nullable()->after('estimated_duration'); // 'preparation', 'processing', 'delivery'
            }
            // Skip is_mandatory and skip_conditions to avoid row size limit
        });

        // Create order_stage_histories table for audit trail (note: plural)
        Schema::create('order_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('work_stage_id')->constrained()->onDelete('cascade');
            $table->string('previous_stage', 100)->nullable();
            $table->string('new_stage', 100)->notNull();
            $table->enum('action', ['start', 'complete', 'skip', 'rollback', 'move'])->notNull();
            $table->foreignId('action_by')->constrained('users');
            $table->timestamp('action_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'action_at']);
            $table->index(['work_stage_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the history table
        Schema::dropIfExists('order_stage_histories');

        // Remove added columns from work_stages
        Schema::table('work_stages', function (Blueprint $table) {
            $table->dropColumn([
                'color',
                'icon',
                'can_skip',
                'requires_role',
                'estimated_duration',
                'stage_group'
            ]);
        });

        // Remove added columns from order_processings
        Schema::table('order_processings', function (Blueprint $table) {
            $table->dropForeign(['skipped_by']);
            $table->dropColumn([
                'stage_color',
                'can_skip',
                'skip_reason',
                'skipped_at',
                'skipped_by',
                'visual_priority',
                'estimated_duration',
                'actual_duration',
                'stage_metadata'
            ]);
        });
    }
};
