<?php

namespace App\Http\Controllers\Admin\ChatCenter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ChatSetting;
use App\Http\Requests\UpdateChatSettingsRequest;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = ChatSetting::firstOrCreate([], [
            'is_message_enabled' => true,
            'message_text' => 'مرحباً، كيف يمكنني مساعدتك؟',
            'display_location' => 'chat_only',
            'theme_color' => '#f59e0b',
            'whatsapp_number' => '01000000000',
            'whatsapp_enabled' => true,
            'whatsapp_default_message' => 'مرحباً، أود الاستفسار عن خدمات Print Express.',
        ]);
        
        return response()->json(['data' => [
            'id' => $settings->id,
            'isMessageEnabled' => (bool)$settings->is_message_enabled,
            'messageText' => $settings->message_text,
            'displayLocation' => $settings->display_location,
            'themeColor' => $settings->theme_color,
            'whatsappNumber' => $settings->whatsapp_number,
            'whatsappEnabled' => (bool)($settings->whatsapp_enabled ?? true),
            'whatsappDefaultMessage' => $settings->whatsapp_default_message,
        ]]);
    }

    public function update(UpdateChatSettingsRequest $request)
    {
        $settings = ChatSetting::first();
        if (!$settings) {
            $settings = new ChatSetting();
        }

        if ($request->has('isMessageEnabled')) {
            $settings->is_message_enabled = $request->input('isMessageEnabled');
        }
        if ($request->has('messageText')) {
            $settings->message_text = $request->input('messageText');
        }
        if ($request->has('displayLocation')) {
            $settings->display_location = $request->input('displayLocation');
        }
        if ($request->has('themeColor')) {
            $settings->theme_color = $request->input('themeColor');
        }
        if ($request->has('whatsappNumber') || $request->has('whatsapp_number')) {
            $settings->whatsapp_number = $request->input('whatsappNumber') ?? $request->input('whatsapp_number');
            // Keep general setting in sync
            \App\Models\Setting::updateOrCreate(['key' => 'whatsapp_number'], ['value' => $settings->whatsapp_number]);
        }
        if ($request->has('whatsappEnabled') || $request->has('whatsapp_enabled')) {
            $settings->whatsapp_enabled = filter_var($request->input('whatsappEnabled') ?? $request->input('whatsapp_enabled'), FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->has('whatsappDefaultMessage') || $request->has('whatsapp_default_message')) {
            $settings->whatsapp_default_message = $request->input('whatsappDefaultMessage') ?? $request->input('whatsapp_default_message');
        }

        $settings->save();

        return response()->json([
            'message' => 'Settings updated successfully',
            'data' => [
                'id' => $settings->id,
                'isMessageEnabled' => (bool)$settings->is_message_enabled,
                'messageText' => $settings->message_text,
                'displayLocation' => $settings->display_location,
                'themeColor' => $settings->theme_color,
                'whatsappNumber' => $settings->whatsapp_number,
                'whatsappEnabled' => (bool)$settings->whatsapp_enabled,
                'whatsappDefaultMessage' => $settings->whatsapp_default_message,
            ]
        ]);
    }
}
