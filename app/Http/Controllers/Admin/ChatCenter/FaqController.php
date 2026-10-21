<?php

namespace App\Http\Controllers\Admin\ChatCenter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Str;
use App\Models\FaqSection;
use App\Models\FaqQuestion;
use App\Http\Requests\StoreFaqSectionRequest;
use App\Http\Requests\StoreFaqQuestionRequest;
use App\Http\Requests\ReorderFaqRequest;

class FaqController extends Controller
{
    public function index()
    {
        $sections = FaqSection::with('questions')->orderBy('order')->get();
        
        $data = $sections->map(function ($section) {
            return [
                'id' => $section->id,
                'title' => $section->title,
                'order' => $section->order,
                'questions' => $section->questions->map(function ($q) {
                    return [
                        'id' => $q->id,
                        'question' => $q->question,
                        'q' => $q->question,
                        'answer' => $q->answer,
                        'a' => $q->answer,
                        'isActive' => (bool)$q->is_active,
                        'order' => $q->order,
                    ];
                })->toArray(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function storeSection(StoreFaqSectionRequest $request)
    {
        $section = FaqSection::create([
            'id' => (string) Str::uuid(),
            'title' => $request->title,
            'order' => $request->order ?? FaqSection::max('order') + 1,
        ]);

        return response()->json([
            'data' => [
                'id' => $section->id,
                'title' => $section->title,
                'order' => $section->order,
                'questions' => []
            ]
        ]);
    }

    public function updateSection(StoreFaqSectionRequest $request, $id)
    {
        $section = FaqSection::findOrFail($id);
        $section->update($request->only(['title']));

        return response()->json([
            'data' => [
                'id' => $section->id,
                'title' => $section->title,
                'order' => $section->order,
            ]
        ]);
    }

    public function destroySection($id)
    {
        $section = FaqSection::findOrFail($id);
        $section->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function storeQuestion(StoreFaqQuestionRequest $request)
    {
        $question = FaqQuestion::create([
            'id' => (string) Str::uuid(),
            'section_id' => $request->sectionId,
            'question' => $request->question,
            'answer' => $request->answer,
            'is_active' => $request->isActive ?? true,
            'order' => $request->order ?? FaqQuestion::where('section_id', $request->sectionId)->max('order') + 1,
        ]);

        return response()->json([
            'data' => [
                'id' => $question->id,
                'question' => $question->question,
                'q' => $question->question,
                'answer' => $question->answer,
                'a' => $question->answer,
                'isActive' => (bool)$question->is_active,
                'order' => $question->order,
            ]
        ]);
    }

    public function updateQuestion(StoreFaqQuestionRequest $request, $id)
    {
        $question = FaqQuestion::findOrFail($id);
        $question->update([
            'section_id' => $request->sectionId ?? $question->section_id,
            'question' => $request->question ?? $question->question,
            'answer' => $request->answer ?? $question->answer,
            'is_active' => $request->has('isActive') ? $request->isActive : $question->is_active,
        ]);

        return response()->json([
            'data' => [
                'id' => $question->id,
                'question' => $question->question,
                'q' => $question->question,
                'answer' => $question->answer,
                'a' => $question->answer,
                'isActive' => (bool)$question->is_active,
                'order' => $question->order,
            ]
        ]);
    }

    public function destroyQuestion($id)
    {
        $question = FaqQuestion::findOrFail($id);
        $question->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function reorder(ReorderFaqRequest $request)
    {
        $type = $request->input('type');
        $items = $request->input('items');

        foreach ($items as $item) {
            if ($type === 'section') {
                FaqSection::where('id', $item['id'])->update(['order' => $item['order']]);
            } else {
                FaqQuestion::where('id', $item['id'])->update(['order' => $item['order']]);
            }
        }

        return response()->json(['message' => 'Reordered successfully']);
    }
}
