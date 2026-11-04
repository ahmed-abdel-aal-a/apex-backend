<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrintingType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'printing_product_type_id',
        'name',
        'description',
        'image',
        'pricing_method',
        'base_price_fixed',
        'base_price_unit',
        'base_price_multiplier',
        'min_quantity',
        'quantity_step',
        'min_chargeable_area',
        'min_chargeable_price',
        'is_active',
        'requires_upload',
        'upload_rules',
        'upload_categories',
        'standard_roll_sizes',
        'max_print_width',
        'max_print_length',
        'bleed_margin',
        'safe_zone',
        'thickness_factor',
        'base_production_days',
        'is_market_sensitive',
        'market_notice',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_market_sensitive' => 'boolean',
        'requires_upload' => 'boolean',
        'upload_categories' => 'array',
        'standard_roll_sizes' => 'array',
        'upload_rules' => 'array',
    ];

    public const STANDARD_UPLOAD_PRESETS = [
        'design' => [
            'id' => 'design',
            'label' => 'ملف التصميم الأساسي',
            'help_text' => 'يرجى رفع ملف بجودة 300 DPI بصيغة PDF أو AI مع زيادة القص 3 مم',
            'is_required' => true,
            'allowed_extensions' => ['pdf', 'ai', 'psd', 'zip', 'rar'],
        ],
        'spot_uv_mask' => [
            'id' => 'spot_uv_mask',
            'label' => 'قناع الورنيش الموضعي (Spot UV)',
            'help_text' => 'ملف متجهي بلون أسود 100% لتحديد أماكن الورنيش الموضعي',
            'is_required' => false,
            'allowed_extensions' => ['pdf', 'ai'],
        ],
        'foil_mask' => [
            'id' => 'foil_mask',
            'label' => 'قناع الفويل / البصمة (Foil)',
            'help_text' => 'ملف متجهي بلون أسود 100% لتحديد أماكن البصمة الحرارية',
            'is_required' => false,
            'allowed_extensions' => ['pdf', 'ai'],
        ],
        'cutting_guide' => [
            'id' => 'cutting_guide',
            'label' => 'دليل القص / الدايكت (Die-cut)',
            'help_text' => 'خطوط القص وريج الخط باللون الأحمر أو خط Spot مخصص',
            'is_required' => false,
            'allowed_extensions' => ['pdf', 'ai'],
        ],
        'emboss_mask' => [
            'id' => 'emboss_mask',
            'label' => 'قناع البروز (Embossing)',
            'help_text' => 'ملف متجهي لتحديد أماكن الكشف والبروز',
            'is_required' => false,
            'allowed_extensions' => ['pdf', 'ai'],
        ],
    ];

    public static function normalizeUploadCategories($categories): array
    {
        if (empty($categories) || !is_array($categories)) {
            return [self::STANDARD_UPLOAD_PRESETS['design']];
        }

        $normalized = [];
        foreach ($categories as $cat) {
            if (is_string($cat)) {
                $trimmed = trim($cat);
                if (isset(self::STANDARD_UPLOAD_PRESETS[$trimmed])) {
                    $normalized[] = self::STANDARD_UPLOAD_PRESETS[$trimmed];
                } else {
                    $normalized[] = [
                        'id' => $trimmed,
                        'label' => $trimmed,
                        'help_text' => null,
                        'is_required' => false,
                        'allowed_extensions' => ['pdf', 'ai', 'psd', 'zip', 'rar'],
                    ];
                }
            } elseif (is_array($cat)) {
                $id = trim(strtolower((string)($cat['id'] ?? '')));
                if (empty($id)) continue;

                $preset = self::STANDARD_UPLOAD_PRESETS[$id] ?? null;
                $label = !empty($cat['label']) ? trim((string)$cat['label']) : ($preset['label'] ?? $id);
                $helpText = isset($cat['help_text']) && trim((string)$cat['help_text']) !== '' ? trim((string)$cat['help_text']) : ($preset['help_text'] ?? null);
                $isRequired = filter_var($cat['is_required'] ?? ($preset['is_required'] ?? false), FILTER_VALIDATE_BOOLEAN);

                $allowedExtensions = [];
                if (!empty($cat['allowed_extensions']) && is_array($cat['allowed_extensions'])) {
                    foreach ($cat['allowed_extensions'] as $ext) {
                        $clean = strtolower(ltrim(trim((string)$ext), '.'));
                        if (!empty($clean)) {
                            $allowedExtensions[] = $clean;
                        }
                    }
                    $allowedExtensions = array_values(array_unique($allowedExtensions));
                }
                if (empty($allowedExtensions)) {
                    $allowedExtensions = $preset['allowed_extensions'] ?? ['pdf', 'ai', 'psd', 'zip', 'rar'];
                }

                $normalized[] = [
                    'id' => $id,
                    'label' => $label,
                    'help_text' => $helpText,
                    'is_required' => $isRequired,
                    'allowed_extensions' => $allowedExtensions,
                ];
            }
        }

        return !empty($normalized) ? $normalized : [self::STANDARD_UPLOAD_PRESETS['design']];
    }

    public function getUploadCategoriesAttribute($value)
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        if (empty($decoded)) {
            return $this->requires_upload ? [self::STANDARD_UPLOAD_PRESETS['design']] : [];
        }
        return self::normalizeUploadCategories($decoded);
    }

    public function productType()
    {
        return $this->belongsTo(PrintingProductType::class, 'printing_product_type_id');
    }

    public function attributes()
    {
        return $this->hasMany(PrintingAttribute::class);
    }

    public function volumeDiscounts()
    {
        return $this->hasMany(PrintingVolumeDiscount::class);
    }
}
