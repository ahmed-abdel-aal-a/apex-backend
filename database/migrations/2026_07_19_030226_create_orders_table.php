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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name')->index();
            $table->string('customer_phone')->index();
            $table->decimal('total', 10, 2);
            $table->integer('items_count');
            $table->string('status')->default('needs_action')->index();
            $table->string('payment_status')->default('unpaid');
            $table->string('shipping_company')->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('shipping_address')->nullable();
            $table->timestamps();
            $table->softDeletes(); // For 'trash' tab functionality
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
