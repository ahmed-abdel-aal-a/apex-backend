<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FileRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $orderItem;
    public $rejectionReason;

    public function __construct($orderItem, $rejectionReason)
    {
        $this->orderItem = $orderItem;
        $this->rejectionReason = $rejectionReason;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('تحديث هام بشأن ملفات طلبك')
                    ->greeting('مرحباً ' . $notifiable->name . '،')
                    ->line('نأسف لإبلاغك بأنه تم رفض الملف المرفوع للمنتج: ' . $this->orderItem->product_name)
                    ->line('سبب الرفض: ' . $this->rejectionReason)
                    ->line('يرجى الدخول إلى حسابك ورفع ملف جديد مطابق للمواصفات المطلوبة.')
                    ->action('عرض الطلب', url('/orders/' . $this->orderItem->order_id))
                    ->line('شكراً لتفهمك وثقتك بنا.');
    }
}
