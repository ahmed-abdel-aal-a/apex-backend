<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlogPostController extends Controller
{
    public function index()
    {
        $posts = BlogPost::orderBy('created_at', 'desc')->paginate(12);
        return response()->json($posts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_posts',
            'category' => 'nullable|string|max:255',
            'status' => 'required|in:published,draft',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string|max:500',
            'canonical_url' => 'nullable|string|max:2048',
            'og_image' => 'nullable|string|max:2048',
            'is_indexable' => 'nullable|boolean',
            'schema_markup' => 'nullable',
            'featured_image' => 'nullable|string|max:2048',
            'video' => 'nullable|string|max:2048',
            'content' => 'nullable|string',
        ]);

        if (empty($validated['slug'])) {
            $baseSlug = Str::slug($validated['title'], '-', null);
            if (empty($baseSlug)) {
                $baseSlug = 'post-' . time();
            }
            $slug = $baseSlug;
            $count = 1;
            while (BlogPost::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$count}";
                $count++;
            }
            $validated['slug'] = $slug;
        }

        $post = BlogPost::create($validated);
        
        $this->triggerRevalidation($post);

        return response()->json($post, 201);
    }

    public function show($id)
    {
        return BlogPost::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $post = BlogPost::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_posts,slug,' . $id,
            'category' => 'nullable|string|max:255',
            'status' => 'required|in:published,draft',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string|max:500',
            'canonical_url' => 'nullable|string|max:2048',
            'og_image' => 'nullable|string|max:2048',
            'is_indexable' => 'nullable|boolean',
            'schema_markup' => 'nullable',
            'featured_image' => 'nullable|string|max:2048',
            'video' => 'nullable|string|max:2048',
            'content' => 'nullable|string',
        ]);

        if (empty($validated['slug'])) {
            // Keep the old slug, do not generate a new one to preserve SEO.
            unset($validated['slug']);
        }

        $post->update($validated);
        
        $this->triggerRevalidation($post);

        return response()->json($post);
    }

    public function destroy($id)
    {
        $post = BlogPost::findOrFail($id);
        $post->delete();
        
        $this->triggerRevalidation($post);

        return response()->json(['message' => 'تم الحذف بنجاح']);
    }

    private function triggerRevalidation(BlogPost $post = null)
    {
        $storefrontUrl = config('app.storefront_url', 'http://localhost:3000');
        $secret = config('app.revalidate_secret', 'my-super-secret-token');

        $payload = ['secret' => $secret];
        if ($post) {
            $payload['id'] = $post->id;
            $payload['slug'] = $post->slug;
        }

        // Send non-blocking request after response is sent to user
        dispatch(function () use ($storefrontUrl, $payload) {
            try {
                Http::timeout(3)->post("{$storefrontUrl}/api/revalidate", $payload);
            } catch (\Exception $e) {
                Log::error('Revalidation webhook failed: ' . $e->getMessage());
            }
        })->afterResponse();
    }
}
