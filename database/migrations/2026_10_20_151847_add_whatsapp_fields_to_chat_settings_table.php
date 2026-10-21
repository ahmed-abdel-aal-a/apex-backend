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
        Schema::table('chat_settings', function (Blueprint $table) {
            $table->string('whatsapp_number')->nullable()->after('theme_color');
            $table->boolean('whatsapp_enabled')->default(true)->after('whatsapp_number');
            $table->text('whatsapp_default_message')->nullable()->after('whatsapp_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_number', 'whatsapp_enabled', 'whatsapp_default_message']);
        });
    }
};
