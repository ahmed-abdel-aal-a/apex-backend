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
        Schema::create('customer_social_accounts', function (Blueprint $table) {
            $table->id();
            // استخدام uuid بما يتوافق مع جدول customers في apex-store-v2
            $table->uuid('customer_id')->index();
            $table->string('provider_name'); // 'google', 'facebook'
            $table->string('provider_id')->unique();
            $table->timestamps();

            // ربط المفتاح الأجنبي مع الحذف التلقائي
            $table->foreign('customer_id')
                  ->references('id')
                  ->on('customers')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_social_accounts');
    }
};
