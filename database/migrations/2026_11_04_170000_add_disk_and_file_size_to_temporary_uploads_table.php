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
        Schema::table('temporary_uploads', function (Blueprint $table) {
            if (!Schema::hasColumn('temporary_uploads', 'disk')) {
                $table->string('disk', 50)->default('local')->after('file_path');
            }
            if (!Schema::hasColumn('temporary_uploads', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('original_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temporary_uploads', function (Blueprint $table) {
            if (Schema::hasColumn('temporary_uploads', 'file_size')) {
                $table->dropColumn('file_size');
            }
            if (Schema::hasColumn('temporary_uploads', 'disk')) {
                $table->dropColumn('disk');
            }
        });
    }
};
