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
        Schema::create('printing_volume_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printing_type_id')->constrained('printing_types')->cascadeOnDelete();
            $table->integer('min_qty');
            $table->integer('max_qty');
            $table->integer('discount_basis_points');
            $table->integer('added_production_days')->default(0);
            $table->timestamps();
            
            $table->unique(['printing_type_id', 'min_qty', 'max_qty'], 'vol_discount_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printing_volume_discounts');
    }
};
