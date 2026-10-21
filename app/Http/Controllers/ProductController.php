<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_unless(auth()->user() && (auth()->user()->hasRole('Super Admin') || auth()->user()->can('manage products')), 403, 'Unauthorized');
                return $next($request);
            }),
        ];
    }

    /**
     * 1. مسار العرض مع الـ Pagination والـ Filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with([
            'category', 
            'variants',
            'stockReservations' => fn($q) => $q->where('expires_at', '>', now()),
            'variants.stockReservations' => fn($q) => $q->where('expires_at', '>', now())
        ]);

        if ($request->has('search') && $request->search != '') {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
        }

        if ($request->has('category_id') && $request->category_id != '') {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->latest()->paginate(10);
        return response()->json($products, 200);
    }

    protected function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        // تجريد حركات التشكيل والتنوين العربية أولاً لضمان عدم تقطيع الكلمات المشكّلة
        $cleanName = preg_replace('/\p{M}+/u', '', $name);

        // دعم الحروف العربية والإنجليزية والأرقام مع استبدال المسافات والرموز بشرطة (-)
        $slug = preg_replace('/[^\p{Arabic}\p{L}\p{N}\p{M}]+/u', '-', mb_strtolower($cleanName));
        $slug = trim((string)$slug, '-');

        if (empty($slug)) {
            $slug = 'product-' . time();
        }

        $baseSlug = $slug;
        $counter = 1;

        while (Product::where('slug', $slug)->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request) {
            
            $validated = $request->validated();
            $slug = !empty($validated['slug']) ? $validated['slug'] : $this->generateUniqueSlug($validated['name']);
            
            $product = Product::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'category_id' => $validated['category_id'],
                'type' => $validated['type'],
                'price' => $validated['price'],
                'discount_price' => $validated['discount_price'] ?? null,
                'sku' => $validated['sku'] ?? null,
                'barcode' => $validated['barcode'] ?? null,
                'track_inventory' => $validated['track_inventory'],
                'is_active' => $validated['is_active'] ?? true,
                'quantity' => $validated['quantity'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'meta_keywords' => $validated['meta_keywords'] ?? null,
                'canonical_url' => $validated['canonical_url'] ?? null,
                'og_image' => $validated['og_image'] ?? null,
                'is_indexable' => $validated['is_indexable'] ?? true,
                'schema_markup' => isset($validated['schema_markup']) ? json_decode($validated['schema_markup'], true) : null,
                'images' => $validated['images'] ?? null,
            ]);

            if ($validated['type'] === 'variable' && !empty($validated['variants'])) {
                foreach ($validated['variants'] as $variantData) {
                    $product->variants()->create([
                        'name' => $variantData['name'] ?? null,
                        'price' => $variantData['price'],
                        'discount_price' => $variantData['discount_price'] ?? null,
                        'sku' => $variantData['sku'] ?? null,
                        'track_inventory' => $variantData['track_inventory'] ?? false,
                        'quantity' => $variantData['quantity'] ?? null,
                        'attributes' => $variantData['attributes'] ?? [],
                    ]);
                }
                $product->syncWithVariants();
            }
            
            // ربط الخدمات الإضافية
            if (isset($validated['addon_group_ids'])) {
                $product->addonGroups()->sync($validated['addon_group_ids']);
            }

            $product->load(['variants', 'addonGroups']);
            return $product;
        });

        Product::bumpProductsCacheVersion();
        Cache::forget("storefront_product_{$product->id}");
        if ($product->slug) {
            Cache::forget("storefront_product_{$product->slug}");
        }

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);
    }

    /**
     * 2. مسار الجلب الفردي
     */
    public function show($id): JsonResponse
    {
        $product = Product::with(['category', 'variants', 'addonGroups'])->findOrFail($id);
        return response()->json($product, 200);
    }

    /**
     * 3. مسار التحديث المعقد (الزتونة كلها هنا)
     */
    public function update(UpdateProductRequest $request, $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $oldSlug = $product->getOriginal('slug') ?? $product->slug;
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $slug = !empty($validated['slug']) 
                ? $validated['slug'] 
                : ($product->slug ?: $this->generateUniqueSlug($validated['name'], $product->id));

            // تجهيز مصفوفة التحديث للحقول الأساسية
            $updateData = [
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'category_id' => $validated['category_id'],
                'type' => $validated['type'],
                'price' => $validated['price'],
                'discount_price' => $validated['discount_price'] ?? null,
                'sku' => $validated['sku'] ?? null,
                'barcode' => $validated['barcode'] ?? null,
                'track_inventory' => $validated['track_inventory'],
                'quantity' => $validated['quantity'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'meta_keywords' => $validated['meta_keywords'] ?? null,
                'canonical_url' => $validated['canonical_url'] ?? null,
                'og_image' => $validated['og_image'] ?? null,
                'is_indexable' => $validated['is_indexable'] ?? true,
                'schema_markup' => isset($validated['schema_markup']) ? json_decode($validated['schema_markup'], true) : null,
            ];

            // حماية الصور: لا نُعدل حقل الصور إلا إذا كان موجوداً صراحة في الـ Request
            if (array_key_exists('images', $validated)) {
                $updateData['images'] = $validated['images'];
            }

            // حماية حالة التفعيل: لا نعدل is_active إلا إذا تم إرسالها صراحة لحماية الحالة القديمة
            if (array_key_exists('is_active', $validated)) {
                $updateData['is_active'] = $validated['is_active'];
            }

            $product->update($updateData);

            // تزامن المتغيرات (Variants Sync)
            if ($validated['type'] === 'variable' && isset($validated['variants'])) {
                // نجمع الـ IDs اللي جاية من الفرونت اند
                $incomingVariantIds = collect($validated['variants'])->pluck('id')->filter()->toArray();
                
                // نحذف المتغيرات القديمة اللي مش موجودة في الـ Request
                $product->variants()->whereNotIn('id', $incomingVariantIds)->delete();

                // نحدث أو ننشئ المتغيرات
                foreach ($validated['variants'] as $variantData) {
                    if (isset($variantData['id'])) {
                        // تحديث متغير موجود
                        $product->variants()->where('id', $variantData['id'])->update([
                            'name' => $variantData['name'] ?? null,
                            'price' => $variantData['price'],
                            'discount_price' => $variantData['discount_price'] ?? null,
                            'sku' => $variantData['sku'] ?? null,
                            'track_inventory' => $variantData['track_inventory'] ?? false,
                            'quantity' => $variantData['quantity'] ?? null,
                            'attributes' => $variantData['attributes'] ?? [],
                        ]);
                    } else {
                        // إنشاء متغير جديد لمنتج موجود
                        $product->variants()->create([
                            'name' => $variantData['name'] ?? null,
                            'price' => $variantData['price'],
                            'discount_price' => $variantData['discount_price'] ?? null,
                            'sku' => $variantData['sku'] ?? null,
                            'track_inventory' => $variantData['track_inventory'] ?? false,
                            'quantity' => $variantData['quantity'] ?? null,
                            'attributes' => $variantData['attributes'] ?? [],
                        ]);
                    }
                }
                $product->syncWithVariants();
            } else {
                // لو نوع المنتج بقى بسيط أو مبعتوش متغيرات خالص، نحذف كل القديم
                $product->variants()->delete();
            }

            // تزامن الخدمات الإضافية مع لمسة الـ Touch
            if (array_key_exists('addon_group_ids', $validated)) {
                $product->addonGroups()->sync($validated['addon_group_ids'] ?? []);
                $product->touch(); // إجبار تحديث updated_at لتشغيل الـ Model Events (Observer/Cache)
            }

            DB::commit();
            Product::bumpProductsCacheVersion();
            Cache::forget("storefront_product_{$product->id}");
            if ($oldSlug) {
                Cache::forget("storefront_product_{$oldSlug}");
            }
            if ($product->slug && $product->slug !== $oldSlug) {
                Cache::forget("storefront_product_{$product->slug}");
            }
            return response()->json($product->load(['variants', 'addonGroups']), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'حدث خطأ أثناء التحديث: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 4. مسار الحذف الصارم مع مسح الصور الفعلية
     */
    public function destroy($id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $productSlug = $product->slug;

        // مسح الصور من السيرفر (اللعبة كلها هنا عشان نفصل الرابط عن المسار كما طلبت)
        if (!empty($product->images) && is_array($product->images)) {
            foreach ($product->images as $imageUrl) {
                // استخراج المسار بشكل آمن جداً باستخدام دوال PHP المدمجة
                $parsedUrl = parse_url($imageUrl, PHP_URL_PATH);
                if ($parsedUrl) {
                    // مسار URL عادة يكون /storage/products/image.jpg
                    // نريد أن نستخرج products/image.jpg فقط ليتم حذفه من ديسك public
                    $relativePath = preg_replace('#^/?storage/#', '', $parsedUrl);
                    
                    if (Storage::disk('public')->exists($relativePath)) {
                        Storage::disk('public')->delete($relativePath);
                    }
                }
            }
        }

        $product->delete(); // المتغيرات هتطير معاه بسبب الـ cascade
        Product::bumpProductsCacheVersion();
        Cache::forget("storefront_product_{$id}");
        if ($productSlug) {
            Cache::forget("storefront_product_{$productSlug}");
        }
        return response()->json(['message' => 'تم حذف المنتج والصور المرتبطة به بنجاح.'], 200);
    }

    /**
     * 5. مسار الحذف المتعدد (Bulk Delete)
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:products,id'
        ]);

        $products = Product::whereIn('id', $request->ids)->get();
        $imagePaths = [];

        foreach ($products as $product) {
            if (!empty($product->images) && is_array($product->images)) {
                foreach ($product->images as $imageUrl) {
                    $parsedUrl = parse_url($imageUrl, PHP_URL_PATH);
                    if ($parsedUrl) {
                        $relativePath = preg_replace('#^/?storage/#', '', $parsedUrl);
                        $imagePaths[] = $relativePath;
                    }
                }
            }
        }

        DB::beginTransaction();
        try {
            // استخدام each لضمان تفعيل Eloquent Events
            $products->each->delete();
            DB::commit();

            if (!empty($imagePaths)) {
                // مسح الصور بعد نجاح الحذف في الداتابيز
                Storage::disk('public')->delete($imagePaths);
            }

            Product::bumpProductsCacheVersion();
            foreach ($request->ids as $deletedId) {
                Cache::forget("storefront_product_{$deletedId}");
            }

            return response()->json(['message' => 'تم الحذف المتعدد بنجاح.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'حدث خطأ أثناء الحذف المتعدد.'], 500);
        }
    }
}
