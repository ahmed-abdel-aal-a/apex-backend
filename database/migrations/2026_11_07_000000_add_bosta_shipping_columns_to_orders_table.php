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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'shipping_provider_id')) {
                $table->string('shipping_provider_id')->nullable()->index()->after('tracking_number');
            }
            if (!Schema::hasColumn('orders', 'shipping_status')) {
                $table->string('shipping_status')->nullable()->after('shipping_provider_id');
            }
            if (!Schema::hasColumn('orders', 'shipping_error_log')) {
                $table->text('shipping_error_log')->nullable()->after('shipping_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('orders', 'shipping_provider_id')) $cols[] = 'shipping_provider_id';
            if (Schema::hasColumn('orders', 'shipping_status')) $cols[] = 'shipping_status';
            if (Schema::hasColumn('orders', 'shipping_error_log')) $cols[] = 'shipping_error_log';

            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
