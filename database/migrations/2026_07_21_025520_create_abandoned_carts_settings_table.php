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
        Schema::create('abandoned_carts_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->integer('consider_abandoned_after_minutes')->default(60);
            $table->integer('frequency_capping_hours')->default(24);
            $table->boolean('enable_push_notifications')->default(false);
            $table->string('push_notification_title')->nullable();
            $table->string('push_notification_body')->nullable();
            $table->text('whatsapp_template')->nullable();
            $table->string('email_subject')->nullable();
            $table->text('email_template')->nullable();
            $table->boolean('enable_discounts')->default(true);
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('discount_value', 8, 2)->default(10);
            $table->integer('anti_abuse_days')->default(30);
            $table->boolean('exclude_sale_items')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abandoned_carts_settings');
    }
};
