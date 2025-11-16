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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('province_en');
            $table->string('province_ar');
            $table->string('mobile_number');
            $table->string('follow_up_person_en');
            $table->string('follow_up_person_ar');
            $table->text('address_en')->nullable();
            $table->text('address_ar')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number')->nullable();
            $table->decimal('credit_limit', 10, 2)->default(0);
            $table->enum('customer_type', ['individual', 'company'])->default('individual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
