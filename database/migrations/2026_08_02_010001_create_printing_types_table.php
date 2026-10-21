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
        Schema::create('printing_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printing_product_type_id')->constrained('printing_product_types')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('pricing_method', ['fixed', 'attributes_only', 'area']);
            $table->bigInteger('base_price_fixed')->default(0);
            $table->bigInteger('base_price_unit')->default(0);
            $table->bigInteger('base_price_multiplier')->default(0);
            $table->integer('min_quantity')->default(1);
            $table->integer('quantity_step')->default(1);
            $table->bigInteger('min_chargeable_area')->default(0);
            $table->bigInteger('min_chargeable_price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_upload')->default(false);
            $table->json('upload_categories')->nullable();
            $table->json('standard_roll_sizes')->nullable();
            $table->integer('max_print_width')->nullable();
            $table->integer('max_print_length')->nullable();
            $table->integer('bleed_margin')->default(0);
            $table->integer('safe_zone')->default(0);
            $table->decimal('thickness_factor', 8, 2)->default(0);
            $table->integer('base_production_days')->default(1);
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
        Schema::dropIfExists('printing_types');
    }
};
