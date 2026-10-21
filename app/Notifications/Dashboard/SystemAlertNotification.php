<?php
namespace App\Notifications\Dashboard;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class SystemAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $title;
    public $body;
    public $url;
    public $type;

    public function __construct($title, $body, $url, $type = 'system_alert')
    {
        $this->title = $title;
        $this->body = $body;
        $this->url = $url;
        $this->type = $type;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->body,
            'url' => $this->url,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->body,
            'url' => $this->url,
        ]);
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->icon('/icons/icon-192x192.png')
            ->body($this->body)
            ->action('عرض التفاصيل', $this->url)
            ->data(['url' => $this->url]);
    }
}
