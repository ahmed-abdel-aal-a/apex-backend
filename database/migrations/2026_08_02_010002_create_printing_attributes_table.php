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
        Schema::create('printing_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printing_type_id')->constrained('printing_types')->cascadeOnDelete();
            $table->string('name');
            $table->string('label');
            $table->enum('type', ['select', 'radio', 'checkbox', 'number', 'multiplier_number']);
            $table->enum('step', ['specifications', 'finishing']);
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->integer('min_value')->nullable();
            $table->integer('max_value')->nullable();
            $table->json('conditional_rules')->nullable();
            $table->string('linked_upload_category')->nullable();
            $table->boolean('is_dimension_width')->default(false);
            $table->boolean('is_dimension_length')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printing_attributes');
    }
};
