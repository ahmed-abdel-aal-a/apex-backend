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
        Schema::create('printing_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printing_attribute_id')->constrained('printing_attributes')->cascadeOnDelete();
            $table->string('value');
            $table->string('label');
            $table->bigInteger('fixed_price')->default(0);
            $table->bigInteger('unit_price')->default(0);
            $table->bigInteger('multiplier_price')->default(0);
            $table->decimal('weight', 8, 4)->default(0);
            $table->enum('weight_type', ['per_unit', 'per_sqm'])->default('per_unit');
            $table->json('tiered_prices')->nullable();
            $table->integer('added_production_days')->default(0);
            $table->string('required_upload_category')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printing_attribute_values');
    }
};
