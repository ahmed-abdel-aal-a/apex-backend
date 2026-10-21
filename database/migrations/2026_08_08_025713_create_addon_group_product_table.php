<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_group_product', function (Blueprint $table) {
            $table->foreignId('addon_group_id')->constrained('addon_groups')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->primary(['addon_group_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_group_product');
    }
};
