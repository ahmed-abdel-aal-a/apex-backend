<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasTenant;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasUuids, HasTenant;

    public function receivesBroadcastNotificationsOn(): string
    {
        return 'App.Models.Customer.'.$this->id;
    }

    protected $fillable = [
        'store_id',
        'is_guest',
        'password',
        'session_id',
        'name',
        'phone',
        'email',
        'ip_address',
        'source',
        'device',
        'visits_count',
        'status',
        'total_orders',
        'total_spent',
        'first_seen',
        'last_seen',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'total_spent' => 'decimal:2',
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
        'total_orders' => 'integer',
        'visits_count' => 'integer',
        'password' => 'hashed',
        'is_guest' => 'boolean',
    ];

    /**
     * دمج جلسة وسجلات الزائر المجهول في حساب العميل المسجل (Identity Stitching)
     * لمنع تكرار الحسابات وتجميع سجل التصفح، السلات، وعدد الزيارات.
     */
    public static function mergeGuestSession(Customer $customer, ?string $sessionId): void
    {
        if (empty($sessionId)) {
            return;
        }

        // البحث عن أي سجل زائر مجهول بنفس معرف الجلسة
        $guest = self::withTrashed()
            ->where('session_id', $sessionId)
            ->where('id', '!=', $customer->id)
            ->where('is_guest', true)
            ->first();

        if ($guest) {
            // 1. ترحيل سجل التصفح بالكامل
            CustomerBrowsingHistory::where('customer_id', $guest->id)
                ->update(['customer_id' => $customer->id]);

            // 2. ترحيل السلات غير المكتملة
            \App\Models\Cart::where('customer_id', $guest->id)
                ->update(['customer_id' => $customer->id]);

            // 3. ترحيل الطلبات السابقة إن وجدت
            \App\Models\Order::where('customer_id', $guest->id)
                ->update(['customer_id' => $customer->id]);

            // 4. دمج عدد الزيارات (visits_count) بدقة تامة
            $guestVisits = max(1, (int) ($guest->visits_count ?? 1));
            if ($customer->wasRecentlyCreated) {
                $customer->visits_count = $guestVisits;
            } else {
                if (!$customer->last_seen || $customer->last_seen->diffInMinutes(now()) >= 30) {
                    $customer->visits_count = (int) ($customer->visits_count ?? 1) + $guestVisits;
                } else {
                    $customer->visits_count = max((int) ($customer->visits_count ?? 1), $guestVisits);
                }
            }

            // 5. الحفاظ على تاريخ أول ظهور
            if ($guest->first_seen && (!$customer->first_seen || $guest->first_seen < $customer->first_seen)) {
                $customer->first_seen = $guest->first_seen;
            }

            // 6. الحفاظ على مصدر الزيارة الأولى إذا لم يكن للعميل مصدر محدد
            if ((!$customer->source || $customer->source === 'direct') && $guest->source && $guest->source !== 'direct') {
                $customer->source = $guest->source;
            }

            // 7. تحديث الجهاز والـ IP إذا كانت غير مسجلة
            if (!$customer->device && $guest->device) {
                $customer->device = $guest->device;
            }
            if (!$customer->ip_address && $guest->ip_address) {
                $customer->ip_address = $guest->ip_address;
            }

            // 8. حذف سجل الزائر المجهول نهائياً لمنع أي تكرار
            $guest->forceDelete();
        }

        // ربط معرف الجلسة الحالية بحساب العميل
        $customer->session_id = $sessionId;
        $customer->last_seen = now();
        $customer->save();

        // التأكد من ربط أي سلة متبقية بنفس معرف الجلسة
        \App\Models\Cart::where('session_id', $sessionId)
            ->whereIn('status', ['active', 'pending'])
            ->update(['customer_id' => $customer->id]);
    }

    /**
     * احتساب عدد جلسات التصفح الفعلية من سجلات التصفح بالاعتماد على نافذة الـ 30 دقيقة
     */
    public function calculateSessionsCount(): int
    {
        $events = $this->browsingHistory()->reorder('visited_at', 'asc')->get();
        if ($events->isEmpty()) {
            return max(1, (int) ($this->visits_count ?? 1));
        }

        $sessions = 0;
        $lastTime = null;

        foreach ($events as $event) {
            $time = \Carbon\Carbon::parse($event->visited_at ?? $event->created_at);
            if (!$time) continue;

            if (!$lastTime || $lastTime->diffInMinutes($time) >= 30) {
                $sessions++;
            }
            $lastTime = $time;
        }

        return max($sessions, (int) ($this->visits_count ?? 1));
    }

    public function browsingHistory(): HasMany
    {
        return $this->hasMany(CustomerBrowsingHistory::class)->orderBy('created_at', 'desc');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class)->orderBy('created_at', 'desc');
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(CustomerSocialAccount::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderBy('created_at', 'desc');
    }
}
