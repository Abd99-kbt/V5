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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('contact_person_en');
            $table->string('contact_person_ar');
            $table->string('email');
            $table->string('phone');
            $table->text('address_en');
            $table->text('address_ar');
            $table->string('tax_number')->nullable();
            $table->string('commercial_register')->nullable();
            $table->decimal('credit_limit', 10, 2)->default(0);
            $table->integer('payment_terms')->default(30); // days
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
