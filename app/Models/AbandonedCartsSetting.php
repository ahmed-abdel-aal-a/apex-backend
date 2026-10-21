<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasTenant;

class AbandonedCartsSetting extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'consider_abandoned_after_minutes',
        'frequency_capping_hours',
        'enable_push_notifications',
        'push_notification_title',
        'push_notification_body',
        'whatsapp_template',
        'email_subject',
        'email_template',
        'enable_discounts',
        'discount_type',
        'discount_value',
        'anti_abuse_days',
        'exclude_sale_items'
    ];

    protected $casts = [
        'enable_push_notifications' => 'boolean',
        'enable_discounts' => 'boolean',
        'exclude_sale_items' => 'boolean',
    ];

    protected $attributes = [
        'consider_abandoned_after_minutes' => 60,
        'frequency_capping_hours' => 24,
        'enable_push_notifications' => false,
        'enable_discounts' => true,
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'anti_abuse_days' => 30,
        'exclude_sale_items' => true,
    ];

    protected static function booted()
    {
        static::saved(function ($model) {
            \Illuminate\Support\Facades\Cache::forget("abandoned_carts_settings_{$model->store_id}");
        });
    }
}
