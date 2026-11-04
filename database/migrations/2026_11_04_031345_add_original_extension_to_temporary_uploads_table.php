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
            if (!Schema::hasColumn('temporary_uploads', 'original_extension')) {
                $table->string('original_extension', 20)->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('temporary_uploads', 'original_name')) {
                $table->string('original_name', 255)->nullable()->after('original_extension');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temporary_uploads', function (Blueprint $table) {
            if (Schema::hasColumn('temporary_uploads', 'original_name')) {
                $table->dropColumn('original_name');
            }
            if (Schema::hasColumn('temporary_uploads', 'original_extension')) {
                $table->dropColumn('original_extension');
            }
        });
    }
};
