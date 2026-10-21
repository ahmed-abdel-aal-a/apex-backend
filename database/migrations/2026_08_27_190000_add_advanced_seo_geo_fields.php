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
        // 1. تحديث جدول المنتجات (Products)
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'meta_keywords')) {
                $table->string('meta_keywords')->nullable()->after('seo_description');
            }
            if (!Schema::hasColumn('products', 'canonical_url')) {
                $table->string('canonical_url')->nullable()->after('meta_keywords');
            }
            if (!Schema::hasColumn('products', 'og_image')) {
                $table->string('og_image')->nullable()->after('canonical_url'); 
            }
            if (!Schema::hasColumn('products', 'is_indexable')) {
                $table->boolean('is_indexable')->default(true)->after('og_image'); 
            }
            if (!Schema::hasColumn('products', 'schema_markup')) {
                $table->json('schema_markup')->nullable()->after('is_indexable');
            }
        });

        // 2. تحديث الأقسام (Categories)
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'slug')) {
                $table->string('slug')->unique()->nullable()->after('name');
            }
            if (!Schema::hasColumn('categories', 'seo_title')) {
                $table->string('seo_title')->nullable()->after('slug');
            }
            if (!Schema::hasColumn('categories', 'seo_description')) {
                $table->text('seo_description')->nullable()->after('seo_title');
            }
            if (!Schema::hasColumn('categories', 'meta_keywords')) {
                $table->string('meta_keywords')->nullable()->after('seo_description');
            }
            if (!Schema::hasColumn('categories', 'canonical_url')) {
                $table->string('canonical_url')->nullable()->after('meta_keywords');
            }
            if (!Schema::hasColumn('categories', 'og_image')) {
                $table->string('og_image')->nullable()->after('canonical_url');
            }
            if (!Schema::hasColumn('categories', 'is_indexable')) {
                $table->boolean('is_indexable')->default(true)->after('og_image');
            }
        });

        // 3. تحديث المقالات (Blog Posts)
        Schema::table('blog_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('blog_posts', 'meta_keywords')) {
                $table->string('meta_keywords')->nullable()->after('meta_description');
            }
            if (!Schema::hasColumn('blog_posts', 'canonical_url')) {
                $table->string('canonical_url')->nullable()->after('meta_keywords');
            }
            if (!Schema::hasColumn('blog_posts', 'og_image')) {
                $table->string('og_image')->nullable()->after('canonical_url');
            }
            if (!Schema::hasColumn('blog_posts', 'is_indexable')) {
                $table->boolean('is_indexable')->default(true)->after('og_image');
            }
            if (!Schema::hasColumn('blog_posts', 'schema_markup')) {
                $table->json('schema_markup')->nullable()->after('is_indexable');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['meta_keywords', 'canonical_url', 'og_image', 'is_indexable', 'schema_markup']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['slug', 'seo_title', 'seo_description', 'meta_keywords', 'canonical_url', 'og_image', 'is_indexable']);
        });
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['meta_keywords', 'canonical_url', 'og_image', 'is_indexable', 'schema_markup']);
        });
    }
};
