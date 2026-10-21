<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChatSetting;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\FaqSection;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Get storefront chat and whatsapp settings
     */
    public function getSettings()
    {
        $settings = ChatSetting::first();
        if (!$settings) {
            $settings = ChatSetting::create([
                'is_message_enabled' => true,
                'message_text' => 'مرحباً، كيف يمكنني مساعدتك اليوم؟',
                'display_location' => 'chat_only',
                'theme_color' => '#f59e0b',
                'whatsapp_number' => '01000000000',
                'whatsapp_enabled' => true,
                'whatsapp_default_message' => 'مرحباً، أود الاستفسار عن خدمات ومنتجات Print Express.',
            ]);
        }

        return response()->json([
            'data' => [
                'isMessageEnabled' => (bool)$settings->is_message_enabled,
                'messageText' => $settings->message_text,
                'displayLocation' => $settings->display_location,
                'themeColor' => $settings->theme_color,
                'whatsappNumber' => $settings->whatsapp_number ?: '01000000000',
                'whatsappEnabled' => (bool)($settings->whatsapp_enabled ?? true),
                'whatsappDefaultMessage' => $settings->whatsapp_default_message ?: 'مرحباً، أود الاستفسار عن خدمات ومنتجات Print Express.',
            ]
        ]);
    }

    /**
     * Get active FAQs for the storefront
     */
    public function getFaqs()
    {
        $sections = FaqSection::with(['questions' => function ($query) {
            $query->where('is_active', true)->orderBy('order');
        }])->orderBy('order')->get();

        $data = $sections->map(function ($section) {
            return [
                'id' => $section->id,
                'title' => $section->title,
                'questions' => $section->questions->map(function ($q) {
                    return [
                        'id' => $q->id,
                        'question' => $q->question,
                        'q' => $q->question,
                        'answer' => $q->answer,
                        'a' => $q->answer,
                    ];
                })->toArray(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Get or create a conversation for the storefront visitor
     */
    public function getOrCreateConversation(Request $request)
    {
        $request->validate([
            'uuid' => 'nullable|string|max:64',
            'name' => 'nullable|string|max:100',
        ]);

        $uuid = $request->input('uuid');
        $name = $request->input('name') ?: 'زائر المتجر';

        $conversation = null;
        if ($uuid) {
            $conversation = ChatConversation::where('uuid', $uuid)->first();
        }

        if (!$conversation) {
            $uuid = $uuid ?: (string) Str::uuid();
            $conversation = ChatConversation::create([
                'uuid' => $uuid,
                'name' => $name,
                'is_online' => true,
                'unread_count' => 0,
            ]);
        } else {
            if ($name && $conversation->name === 'زائر المتجر' && $name !== 'زائر المتجر') {
                $conversation->update(['name' => $name]);
            }
        }

        return response()->json([
            'data' => [
                'id' => (string)$conversation->id,
                'uuid' => $conversation->uuid,
                'name' => $conversation->name,
                'isOnline' => (bool)$conversation->is_online,
            ]
        ]);
    }

    /**
     * Get all messages for a specific conversation
     */
    public function getMessages($uuid)
    {
        $conversation = ChatConversation::where('uuid', $uuid)->firstOrFail();
        $messages = $conversation->messages()->oldest()->get();

        $data = $messages->map(function ($msg) {
            return [
                'id' => (string)$msg->id,
                'text' => $msg->text,
                'sender' => $msg->sender,
                'isImage' => (bool)$msg->is_image,
                'time' => $msg->created_at->format('g:i A'),
            ];
        });

        return response()->json([
            'data' => $data,
            'conversation' => [
                'id' => (string)$conversation->id,
                'uuid' => $conversation->uuid,
                'name' => $conversation->name,
                'isOnline' => (bool)$conversation->is_online,
            ]
        ]);
    }

    /**
     * Store a message from the storefront customer
     */
    public function storeMessage(Request $request, $uuid)
    {
        $conversation = ChatConversation::where('uuid', $uuid)->firstOrFail();

        $request->validate([
            'text' => 'required_without:image|nullable|string|max:2000',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $messageData = [
            'conversation_id' => $conversation->id,
            'sender' => 'customer',
            'is_image' => false,
        ];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('chat_images', 'public');
            $messageData['text'] = asset('storage/' . $path);
            $messageData['is_image'] = true;
        } else {
            $messageData['text'] = $request->input('text');
        }

        $message = ChatMessage::create($messageData);

        // Update conversation activity and increase unread count for admin
        $conversation->increment('unread_count');
        $conversation->update(['is_online' => true]);
        $conversation->touch();

        return response()->json([
            'data' => [
                'id' => (string)$message->id,
                'text' => $message->text,
                'sender' => $message->sender,
                'isImage' => (bool)$message->is_image,
                'time' => $message->created_at->format('g:i A'),
            ]
        ], 201);
    }
}
