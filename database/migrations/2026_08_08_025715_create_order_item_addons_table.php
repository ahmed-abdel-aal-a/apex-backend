<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('product_addon_id')->nullable()->constrained('product_addons')->nullOnDelete();
            $table->json('name_snapshot');
            $table->decimal('price_snapshot', 10, 2);
            $table->string('price_type_snapshot');
            $table->integer('quantity')->default(1);
            $table->json('options_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_addons');
    }
};
