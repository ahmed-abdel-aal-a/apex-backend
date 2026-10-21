<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_group_id')->constrained('addon_groups')->cascadeOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->enum('type', ['simple', 'printing_linked'])->default('simple');
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('price_type', ['fixed', 'percentage'])->default('fixed');
            $table->foreignId('printing_type_id')->nullable()->constrained('printing_types')->nullOnDelete();
            $table->boolean('requires_upload')->default(false);
            $table->boolean('track_inventory')->default(false);
            $table->integer('quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_addons');
    }
};
