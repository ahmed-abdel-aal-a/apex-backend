<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $limit = min((int) $request->input('limit', $request->input('per_page', 12)), 50);
        if ($limit <= 0) {
            $limit = 12;
        }

        $posts = BlogPost::where('status', 'published')
                         ->orderBy('created_at', 'desc')
                         ->paginate($limit);
                         
        return response()->json($posts);
    }

    public function show($idOrSlug)
    {
        $decoded = urldecode($idOrSlug);

        $post = BlogPost::where('status', 'published')
                        ->where(function($query) use ($idOrSlug, $decoded) {
                            $query->where('id', $idOrSlug)
                                  ->orWhere('slug', $idOrSlug)
                                  ->orWhere('slug', $decoded);
                        })
                        ->firstOrFail();

        return response()->json($post);
    }
}
