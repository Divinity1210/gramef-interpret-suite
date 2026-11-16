<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RateCard extends Model
{
    protected $fillable = [
        'name',
        'role_type',
        'service_type',
        'qualification_level',
        'daytime_rate',
        'ooh_rate',
        'minimum_hours',
        'travel_rate_hourly',
        'mileage_rate',
        'cancellation_min_hours',
        'is_default',
    ];

    protected $casts = [
        'daytime_rate' => 'decimal:2',
        'ooh_rate' => 'decimal:2',
        'minimum_hours' => 'decimal:2',
        'travel_rate_hourly' => 'decimal:2',
        'mileage_rate' => 'decimal:2',
        'cancellation_min_hours' => 'decimal:2',
        'is_default' => 'boolean',
    ];
}
