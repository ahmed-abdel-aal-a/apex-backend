<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            
            return response()->json([
                'url' => asset('storage/' . $path)
            ], 201);
        }

        return response()->json(['error' => 'لم يتم إرسال أي صورة.'], 400);
    }

    public function uploadGeneral(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:5120'],
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = \Illuminate\Support\Str::uuid() . '.' . $image->extension();
            $path = $image->storeAs('blog', $filename, 'public');
            
            return response()->json([
                'url' => asset('storage/' . $path)
            ], 201);
        }

        return response()->json(['error' => 'لم يتم إرسال أي صورة.'], 400);
    }
}
