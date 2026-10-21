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
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('item_type')->default('standard');
            $table->foreignId('printing_type_id')->nullable()->constrained('printing_types')->nullOnDelete();
            $table->string('custom_product_name')->nullable();
            $table->json('printing_options')->nullable();
            $table->json('uploaded_files')->nullable();
            $table->string('file_status')->default('not_applicable');
            $table->text('file_rejection_reason')->nullable();
            $table->date('estimated_delivery_date')->nullable();
            $table->integer('tax_basis_points_snapshot')->default(0);
            $table->foreignId('source_order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['printing_type_id']);
            $table->dropForeign(['source_order_item_id']);
            $table->dropColumn([
                'item_type',
                'printing_type_id',
                'custom_product_name',
                'printing_options',
                'uploaded_files',
                'file_status',
                'file_rejection_reason',
                'estimated_delivery_date',
                'tax_basis_points_snapshot',
                'source_order_item_id'
            ]);
        });
    }
};
