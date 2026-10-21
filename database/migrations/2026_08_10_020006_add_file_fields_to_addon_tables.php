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
        Schema::table('cart_item_addons', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('total');
            $table->string('temp_file_id')->nullable()->after('file_path');
        });

        Schema::table('order_item_addons', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('total');
            $table->string('temp_file_id')->nullable()->after('file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_item_addons', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'temp_file_id']);
        });

        Schema::table('order_item_addons', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'temp_file_id']);
        });
    }
};
