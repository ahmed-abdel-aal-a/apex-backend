<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChatSetting;
use App\Models\FaqSection;
use App\Models\FaqQuestion;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Support\Str;

class ChatCenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Chat Settings
        ChatSetting::firstOrCreate([], [
            'is_message_enabled' => true,
            'message_text' => 'مرحباً! كيف يمكننا مساعدتك اليوم؟',
            'display_location' => 'bottom-right',
            'theme_color' => '#007BFF'
        ]);

        // 2. Seed FAQ Sections
        $sec1 = FaqSection::firstOrCreate(['id' => 'sec-1'], ['title' => 'الطلبات والشحن', 'order' => 1]);
        $sec2 = FaqSection::firstOrCreate(['id' => 'sec-2'], ['title' => 'الدفع والاسترجاع', 'order' => 2]);

        // 3. Seed FAQ Questions
        FaqQuestion::firstOrCreate(['id' => 'q-1'], [
            'section_id' => $sec1->id,
            'question' => 'متى سيصل طلبي؟',
            'answer' => 'يستغرق الشحن عادة من يومين إلى خمسة أيام عمل حسب مدينتك.',
            'is_active' => true,
            'order' => 1
        ]);
        
        FaqQuestion::firstOrCreate(['id' => 'q-2'], [
            'section_id' => $sec1->id,
            'question' => 'هل يمكنني تتبع الشحنة؟',
            'answer' => 'نعم، ستصلك رسالة برقم التتبع بمجرد خروج الطلب من المستودع.',
            'is_active' => true,
            'order' => 2
        ]);

        FaqQuestion::firstOrCreate(['id' => 'q-3'], [
            'section_id' => $sec2->id,
            'question' => 'ما هي طرق الدفع المتاحة؟',
            'answer' => 'نقبل الدفع بالبطاقات الائتمانية، مدى، والدفع عند الاستلام.',
            'is_active' => true,
            'order' => 1
        ]);

        // 4. Seed Chat Conversations
        $conv1 = ChatConversation::create([
            'name' => 'أحمد محمد',
            'uuid' => Str::uuid(),
            'is_online' => true,
            'unread_count' => 1
        ]);

        $conv2 = ChatConversation::create([
            'name' => 'سارة أحمد',
            'uuid' => Str::uuid(),
            'is_online' => false,
            'unread_count' => 0
        ]);

        // 5. Seed Chat Messages
        ChatMessage::create([
            'conversation_id' => $conv1->id,
            'text' => 'السلام عليكم، هل المنتج متوفر؟',
            'sender' => 'customer',
            'is_image' => false
        ]);
        
        ChatMessage::create([
            'conversation_id' => $conv1->id,
            'text' => 'وعليكم السلام، نعم المنتج متوفر وسيتم شحنه فور إتمام الطلب.',
            'sender' => 'agent',
            'is_image' => false
        ]);

        ChatMessage::create([
            'conversation_id' => $conv1->id,
            'text' => 'شكراً لك.',
            'sender' => 'customer',
            'is_image' => false
        ]);

        ChatMessage::create([
            'conversation_id' => $conv2->id,
            'text' => 'كيف يمكنني استرجاع منتج؟',
            'sender' => 'customer',
            'is_image' => false
        ]);
    }
}
