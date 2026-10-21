<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_unless(
                    auth()->user() && (
                        auth()->user()->hasRole('Super Admin') ||
                        auth()->user()->can('manage categories')
                    ),
                    403,
                    'Unauthorized'
                );
                return $next($request);
            }),
        ];
    }

    public function index(): JsonResponse
    {
        $categories = Category::with('addonGroups')->get();
        return response()->json($categories, 200);
    }

    protected function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        // تجريد حركات التشكيل والتنوين العربية أولاً لضمان عدم تقطيع الكلمات المشكّلة
        $cleanName = preg_replace('/\p{M}+/u', '', $name);

        // دعم الحروف العربية والإنجليزية والأرقام مع استبدال المسافات والرموز بشرطة (-)
        $slug = preg_replace('/[^\p{Arabic}\p{L}\p{N}\p{M}]+/u', '-', mb_strtolower($cleanName));
        $slug = trim((string)$slug, '-');

        if (empty($slug)) {
            $slug = 'category-' . time();
        }

        $baseSlug = $slug;
        $counter = 1;

        while (Category::where('slug', $slug)->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'is_active' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'starts_with:http://,https://'],
            'og_image' => ['nullable', 'string'],
            'is_indexable' => ['nullable', 'boolean'],
            'addon_group_ids' => ['nullable', 'array'],
            'addon_group_ids.*' => ['integer', 'exists:addon_groups,id'],
        ]);

        $slug = !empty($validated['slug']) ? $validated['slug'] : $this->generateUniqueSlug($validated['name']);

        $categoryData = Arr::only($validated, [
            'name', 'parent_id', 'seo_title', 'seo_description', 
            'meta_keywords', 'canonical_url', 'og_image'
        ]);
        $categoryData['slug'] = $slug;
        $categoryData['is_active'] = $validated['is_active'] ?? true;
        $categoryData['is_indexable'] = $validated['is_indexable'] ?? true;

        $category = Category::create($categoryData);

        if (!empty($validated['addon_group_ids'])) {
            $category->addonGroups()->sync($validated['addon_group_ids']);
        }

        \App\Models\Product::bumpProductsCacheVersion();

        return response()->json($category->load('addonGroups'), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug,' . $id],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'is_active' => ['sometimes', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'starts_with:http://,https://'],
            'og_image' => ['nullable', 'string'],
            'is_indexable' => ['nullable', 'boolean'],
            'addon_group_ids' => ['nullable', 'array'],
            'addon_group_ids.*' => ['integer', 'exists:addon_groups,id'],
        ]);

        // حماية من جعل التصنيف أباً لنفسه أو التورط في حلقة دائرية (Circular Reference)
        if (isset($validated['parent_id']) && !empty($validated['parent_id'])) {
            $targetParentId = (int)$validated['parent_id'];
            if ($targetParentId === (int)$id) {
                return response()->json(['error' => 'لا يمكن جعل التصنيف أباً لنفسه.'], 422);
            }

            // فحص شجرة الآباء للتصنيف المختار لمنع الحلقات المفرغة
            $currentParent = Category::find($targetParentId);
            $visited = [(int)$id];
            while ($currentParent) {
                if (in_array($currentParent->id, $visited, true)) {
                    return response()->json([
                        'error' => 'لا يمكن اختيار تصنيف فرعي كأب لهذا التصنيف لأن ذلك يسبب حلقة دائرية مفرغة.'
                    ], 422);
                }
                $visited[] = $currentParent->id;
                $currentParent = $currentParent->parent_id ? Category::find($currentParent->parent_id) : null;
            }
        }

        $categoryData = Arr::only($validated, [
            'name', 'parent_id', 'seo_title', 'seo_description', 
            'meta_keywords', 'canonical_url', 'og_image'
        ]);

        // إذا أُرسل slug صراحة نحدثه، وإلا نحتفظ بالـ slug الحالي للقسم دون إعادة توليده
        if (!empty($validated['slug'])) {
            $categoryData['slug'] = $validated['slug'];
        } elseif (empty($category->slug)) {
            $categoryData['slug'] = $this->generateUniqueSlug($validated['name'], $category->id);
        }

        if (array_key_exists('is_active', $validated)) {
            $categoryData['is_active'] = $validated['is_active'];
        }
        if (array_key_exists('is_indexable', $validated)) {
            $categoryData['is_indexable'] = $validated['is_indexable'];
        }

        $category->update($categoryData);

        if ($request->has('addon_group_ids')) {
            $category->addonGroups()->sync($validated['addon_group_ids'] ?? []);
        }

        \App\Models\Product::bumpProductsCacheVersion();

        return response()->json($category->load('addonGroups'));
    }

    public function destroy($id): JsonResponse
    {
        $category = Category::withCount(['children', 'products'])->findOrFail($id);

        if ($category->children_count > 0) {
            return response()->json(['error' => 'لا يمكن حذف تصنيف يحتوي على تصنيفات فرعية.'], 400);
        }
        if ($category->products_count > 0) {
            return response()->json(['error' => 'لا يمكن حذف هذا التصنيف لوجود منتجات مرتبطة به.'], 400);
        }

        $category->addonGroups()->detach();
        $category->delete();

        \App\Models\Product::bumpProductsCacheVersion();

        return response()->json(['message' => 'تم حذف التصنيف بنجاح.'], 200);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:categories,id',
        ]);

        $categories = Category::whereIn('id', $request->ids)
            ->withCount(['children', 'products'])
            ->get();

        $deletable = [];
        $blocked = [];

        foreach ($categories as $cat) {
            if ($cat->children_count > 0) {
                $blocked[] = [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'reason' => 'يحتوي على تصنيفات فرعية',
                ];
            } elseif ($cat->products_count > 0) {
                $blocked[] = [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'reason' => 'مرتبط بمنتجات',
                ];
            } else {
                $deletable[] = $cat;
            }
        }

        if (empty($deletable)) {
            return response()->json([
                'message' => 'لم يتم حذف أي تصنيف.',
                'deleted_count' => 0,
                'blocked' => $blocked,
            ], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($deletable as $cat) {
                $cat->addonGroups()->detach();
                $cat->delete();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'حدث خطأ أثناء الحذف الجماعي.'], 500);
        }

        \App\Models\Product::bumpProductsCacheVersion();

        return response()->json([
            'message' => 'تم حذف ' . count($deletable) . ' تصنيف بنجاح.',
            'deleted_count' => count($deletable),
            'blocked' => $blocked,
        ]);
    }
}
