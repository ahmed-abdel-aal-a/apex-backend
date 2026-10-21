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
        // 1. Add fields to printing_types
        Schema::table('printing_types', function (Blueprint $table) {
            if (!Schema::hasColumn('printing_types', 'is_market_sensitive')) {
                $table->boolean('is_market_sensitive')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('printing_types', 'market_notice')) {
                $table->string('market_notice')->nullable()->after('is_market_sensitive');
            }
        });

        // 2. Add fields to orders
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'payment_token')) {
                $table->string('payment_token', 64)->nullable()->unique()->index()->after('payment_method');
            }
            if (!Schema::hasColumn('orders', 'payment_token_expires_at')) {
                $table->timestamp('payment_token_expires_at')->nullable()->after('payment_token');
            }
            if (!Schema::hasColumn('orders', 'price_confirmed_at')) {
                $table->timestamp('price_confirmed_at')->nullable()->after('payment_token_expires_at');
            }
            if (!Schema::hasColumn('orders', 'price_expires_at')) {
                $table->timestamp('price_expires_at')->nullable()->after('price_confirmed_at');
            }
            if (!Schema::hasColumn('orders', 'payment_session_expires_at')) {
                $table->timestamp('payment_session_expires_at')->nullable()->after('price_expires_at');
            }
            if (!Schema::hasColumn('orders', 'admin_price_notes')) {
                $table->text('admin_price_notes')->nullable()->after('payment_session_expires_at');
            }
        });

        // 3. Add fields to order_items
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'requires_price_confirmation')) {
                $table->boolean('requires_price_confirmation')->default(false)->after('item_type');
            }
            if (!Schema::hasColumn('order_items', 'price_confirmation_status')) {
                $table->string('price_confirmation_status', 30)->default('not_applicable')->after('requires_price_confirmation');
            }
            if (!Schema::hasColumn('order_items', 'original_line_total')) {
                $table->decimal('original_line_total', 10, 2)->nullable()->after('total');
            }
            if (!Schema::hasColumn('order_items', 'confirmed_line_total')) {
                $table->decimal('confirmed_line_total', 10, 2)->nullable()->after('original_line_total');
            }
            if (!Schema::hasColumn('order_items', 'line_difference')) {
                $table->decimal('line_difference', 10, 2)->nullable()->after('confirmed_line_total');
            }
            if (!Schema::hasColumn('order_items', 'admin_notes')) {
                $table->string('admin_notes')->nullable()->after('line_difference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $cols = [];
            foreach (['admin_notes', 'line_difference', 'confirmed_line_total', 'original_line_total', 'price_confirmation_status', 'requires_price_confirmation'] as $col) {
                if (Schema::hasColumn('order_items', $col)) $cols[] = $col;
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $cols = [];
            foreach (['admin_price_notes', 'payment_session_expires_at', 'price_expires_at', 'price_confirmed_at', 'payment_token_expires_at', 'payment_token'] as $col) {
                if (Schema::hasColumn('orders', $col)) $cols[] = $col;
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('printing_types', function (Blueprint $table) {
            $cols = [];
            foreach (['market_notice', 'is_market_sensitive'] as $col) {
                if (Schema::hasColumn('printing_types', $col)) $cols[] = $col;
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
