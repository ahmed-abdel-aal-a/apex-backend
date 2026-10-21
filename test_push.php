<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Notifications\Dashboard\SystemAlertNotification;
use Illuminate\Support\Facades\Notification;

$users = User::whereHas('pushSubscriptions')->get();
if($users->count() > 0) {
    Notification::sendNow($users, new SystemAlertNotification('🎉 إشعار من Antigravity', 'الإشعارات تعمل الآن بنجاح عبر النظام بالكامل!', '/'));
    echo 'Notification sent successfully to ' . $users->count() . ' users.';
} else {
    echo 'No users with push subscriptions found.';
}
