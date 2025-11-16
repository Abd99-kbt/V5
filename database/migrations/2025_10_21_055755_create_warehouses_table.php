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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('code')->unique();
            $table->text('address_en');
            $table->text('address_ar');
            $table->string('phone')->nullable();
            $table->string('manager_name')->nullable();
            $table->enum('type', ['main', 'scrap', 'sorting', 'custody'])->default('main');
            $table->decimal('total_capacity', 10, 2)->default(0); // total storage capacity in tons
            $table->decimal('used_capacity', 10, 2)->default(0); // currently used capacity in tons
            $table->decimal('reserved_capacity', 10, 2)->default(0); // reserved capacity in tons
            $table->boolean('is_active')->default(true);
            $table->boolean('is_main')->default(false); // main warehouse flag
            $table->boolean('accepts_transfers')->default(true);
            $table->boolean('requires_approval')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
