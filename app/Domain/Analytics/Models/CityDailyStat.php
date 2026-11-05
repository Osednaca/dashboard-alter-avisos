<?php

namespace App\Domain\Analytics\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'city', 'stat_date', 'playbacks_count', 'unique_devices',
    'unique_businesses', 'total_duration',
])]
class CityDailyStat extends Model
{
    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'playbacks_count' => 'integer',
            'unique_devices' => 'integer',
            'unique_businesses' => 'integer',
            'total_duration' => 'integer',
        ];
    }
}
