<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Explicitly modify payment_status column if supported by driver
            if (DB::getDriverName() !== 'sqlite') {
                $table->string('payment_status', 50)->default('unpaid')->change();
            }

            if (!Schema::hasColumn('orders', 'payment_type')) {
                $table->string('payment_type', 30)->default('full')->after('payment_method');
            }
            if (!Schema::hasColumn('orders', 'deposit_percentage')) {
                $table->decimal('deposit_percentage', 5, 2)->nullable()->after('payment_type');
            }
            if (!Schema::hasColumn('orders', 'deposit_amount')) {
                $table->decimal('deposit_amount', 10, 2)->default(0.00)->after('deposit_percentage');
            }
            if (!Schema::hasColumn('orders', 'remaining_amount')) {
                $table->decimal('remaining_amount', 10, 2)->default(0.00)->after('deposit_amount');
            }
            if (!Schema::hasColumn('orders', 'deposit_paid_at')) {
                $table->timestamp('deposit_paid_at')->nullable()->after('remaining_amount');
            }
            if (!Schema::hasColumn('orders', 'remaining_paid_at')) {
                $table->timestamp('remaining_paid_at')->nullable()->after('deposit_paid_at');
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
            if (Schema::hasColumn('orders', 'remaining_paid_at')) $cols[] = 'remaining_paid_at';
            if (Schema::hasColumn('orders', 'deposit_paid_at')) $cols[] = 'deposit_paid_at';
            if (Schema::hasColumn('orders', 'remaining_amount')) $cols[] = 'remaining_amount';
            if (Schema::hasColumn('orders', 'deposit_amount')) $cols[] = 'deposit_amount';
            if (Schema::hasColumn('orders', 'deposit_percentage')) $cols[] = 'deposit_percentage';
            if (Schema::hasColumn('orders', 'payment_type')) $cols[] = 'payment_type';

            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
