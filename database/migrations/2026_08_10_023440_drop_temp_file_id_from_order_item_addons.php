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
        Schema::table('order_item_addons', function (Blueprint $table) {
            $table->dropColumn('temp_file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_item_addons', function (Blueprint $table) {
            $table->string('temp_file_id')->nullable()->after('file_path');
        });
    }
};
