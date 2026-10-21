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
        Schema::create('customer_browsing_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('store_id')->index();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('path');
            $table->string('title');
            $table->enum('type', ['enter', 'view', 'add_to_cart', 'checkout', 'purchase', 'exit']);
            $table->timestamp('visited_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_browsing_histories');
    }
};
