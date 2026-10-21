<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. إعدادات الخدمة الإضافية لدعم الرفع المتعدد
        Schema::table('product_addons', function (Blueprint $table) {
            $table->boolean('allow_multiple_uploads')->default(false)->after('requires_upload');
            $table->integer('max_uploads')->nullable()->default(1)->after('allow_multiple_uploads');
        });

        // 2. تحديث جدول الرفع المؤقت لتتبع الـ Disk
        Schema::table('temporary_uploads', function (Blueprint $table) {
            $table->string('disk')->default(config('filesystems.default'))->after('file_path');
        });

        // 3. سلة المشتريات: إضافة حقول المصفوفات والديسك (التحضير السحابي)
        Schema::table('cart_item_addons', function (Blueprint $table) {
            $table->string('disk')->default(config('filesystems.default'))->after('options_snapshot');
            $table->json('temp_file_ids')->nullable()->after('disk');
            $table->json('file_paths')->nullable()->after('temp_file_ids');
        });

        // نقل بيانات سلة المشتريات القديمة (Data Migration)
        DB::statement("UPDATE cart_item_addons SET temp_file_ids = JSON_ARRAY(temp_file_id) WHERE temp_file_id IS NOT NULL AND temp_file_id != ''");
        DB::statement("UPDATE cart_item_addons SET file_paths = JSON_ARRAY(file_path) WHERE file_path IS NOT NULL AND file_path != ''");

        // 4. الطلبات المؤكدة: إضافة حقل المصفوفات والديسك
        Schema::table('order_item_addons', function (Blueprint $table) {
            $table->string('disk')->default(config('filesystems.default'))->after('quantity');
            $table->json('file_paths')->nullable()->after('disk');
        });

        // نقل بيانات الطلبات القديمة (Data Migration)
        DB::statement("UPDATE order_item_addons SET file_paths = JSON_ARRAY(file_path) WHERE file_path IS NOT NULL AND file_path != ''");

        // 5. حذف الأعمدة القديمة
        Schema::table('cart_item_addons', function (Blueprint $table) {
            $table->dropColumn(['temp_file_id', 'file_path']);
        });

        Schema::table('order_item_addons', function (Blueprint $table) {
            $table->dropColumn(['file_path']);
        });
    }

    public function down(): void
    {
        Schema::table('cart_item_addons', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('cart_item_id');
            $table->string('temp_file_id')->nullable()->after('file_path');
        });
        
        Schema::table('order_item_addons', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('order_item_id');
        });

        // إرجاع أول ملف فقط من المصفوفة إلى الحقل المفرد (كإجراء طوارئ)
        DB::statement("UPDATE cart_item_addons SET temp_file_id = JSON_UNQUOTE(JSON_EXTRACT(temp_file_ids, '$[0]')) WHERE JSON_LENGTH(temp_file_ids) > 0");
        DB::statement("UPDATE cart_item_addons SET file_path = JSON_UNQUOTE(JSON_EXTRACT(file_paths, '$[0]')) WHERE JSON_LENGTH(file_paths) > 0");
        DB::statement("UPDATE order_item_addons SET file_path = JSON_UNQUOTE(JSON_EXTRACT(file_paths, '$[0]')) WHERE JSON_LENGTH(file_paths) > 0");

        Schema::table('cart_item_addons', function (Blueprint $table) {
            $table->dropColumn(['disk', 'temp_file_ids', 'file_paths']);
        });
        
        Schema::table('order_item_addons', function (Blueprint $table) {
            $table->dropColumn(['disk', 'file_paths']);
        });

        Schema::table('temporary_uploads', function (Blueprint $table) {
            $table->dropColumn('disk');
        });

        Schema::table('product_addons', function (Blueprint $table) {
            $table->dropColumn(['allow_multiple_uploads', 'max_uploads']);
        });
    }
};
