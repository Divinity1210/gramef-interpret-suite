<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    protected $fillable = [
        'slug',
        'brand_name',
        'brand_tagline',
        'logo_text',
        'primary_color',
        'accent_color',
        'currency_symbol',
        'default_vat_rate',
        'chaser_mode',
        'active_delivery_stage',
        'features_json',
        'is_active',
    ];

    protected $casts = [
        'default_vat_rate' => 'decimal:2',
        'active_delivery_stage' => 'integer',
        'features_json' => 'array',
        'is_active' => 'boolean',
    ];

    public static function current(): self
    {
        $active = static::where('is_active', true)->first() ?? static::first();
        if ($active) {
            return $active;
        }

        return new static([
            'slug' => 'gramef',
            'brand_name' => 'GRAMEF InterpretFlow',
            'brand_tagline' => 'Enterprise Language Operations, Automated Billing & Credit Control',
            'logo_text' => 'GRAMEF',
            'primary_color' => '#2563EB',
            'accent_color' => '#10B981',
            'currency_symbol' => '£',
            'default_vat_rate' => 20.00,
            'chaser_mode' => 'review',
            'active_delivery_stage' => 4,
            'is_active' => true,
        ]);
    }
}
