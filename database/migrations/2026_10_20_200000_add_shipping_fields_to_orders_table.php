<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'shipping_method')) {
                $table->string('shipping_method')->default('standard')->after('shipping_cost');
            }
            if (!Schema::hasColumn('orders', 'cod_fee')) {
                $table->decimal('cod_fee', 10, 2)->default(0.00)->after('shipping_cost');
            }
            if (!Schema::hasColumn('orders', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->default(0.00)->after('cod_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('orders', 'shipping_method')) $cols[] = 'shipping_method';
            if (Schema::hasColumn('orders', 'cod_fee')) $cols[] = 'cod_fee';
            if (Schema::hasColumn('orders', 'tax_amount')) $cols[] = 'tax_amount';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
