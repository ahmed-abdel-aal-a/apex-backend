<?php

namespace App\Http\Controllers\Admin\ChatCenter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Http\Requests\StoreChatMessageRequest;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function index()
    {
        $conversations = ChatConversation::with('messages')->latest('updated_at')->get();
        
        $data = $conversations->map(function ($conv) {
            $lastMsg = $conv->messages->last();
            return [
                'id' => (string)$conv->id,
                'name' => $conv->name,
                'isOnline' => (bool)$conv->is_online,
                'online' => (bool)$conv->is_online,
                'unreadCount' => $conv->unread_count,
                'unread' => $conv->unread_count,
                'lastMessage' => $lastMsg ? ($lastMsg->is_image ? '📷 صورة' : $lastMsg->text) : 'بدأت المحادثة',
                'time' => $lastMsg ? $lastMsg->created_at->format('g:i A') : $conv->created_at->format('g:i A'),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function messages($id)
    {
        $conversation = ChatConversation::findOrFail($id);
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

        return response()->json(['data' => $data]);
    }

    public function storeMessage(StoreChatMessageRequest $request, $id)
    {
        $conversation = ChatConversation::findOrFail($id);
        
        $messageData = [
            'conversation_id' => $conversation->id,
            'sender' => 'agent',
            'is_image' => false,
        ];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('chat_images', 'public');
            $messageData['text'] = asset('storage/' . $path);
            $messageData['is_image'] = true;
        } else {
            $messageData['text'] = $request->text;
        }

        $message = ChatMessage::create($messageData);
        
        $conversation->touch(); // Update updated_at

        return response()->json([
            'data' => [
                'id' => (string)$message->id,
                'text' => $message->text,
                'sender' => $message->sender,
                'isImage' => (bool)$message->is_image,
                'time' => $message->created_at->format('g:i A'),
            ]
        ]);
    }

    public function markAsRead($id)
    {
        $conversation = ChatConversation::findOrFail($id);
        $conversation->update(['unread_count' => 0]);
        return response()->json(['message' => 'Conversation marked as read']);
    }

    public function endConversation($id)
    {
        $conversation = ChatConversation::findOrFail($id);
        $conversation->update(['is_online' => false]);
        return response()->json(['message' => 'Conversation ended']);
    }

    public function destroy($id)
    {
        $conversation = ChatConversation::findOrFail($id);
        $conversation->delete();
        return response()->json(['message' => 'Conversation deleted']);
    }
}
