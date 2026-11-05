<?php

namespace App\Domain\Operations\Models;

use App\Domain\Operations\Enums\AlertSeverity;
use App\Domain\Operations\Enums\AlertStatus;
use App\Domain\Operations\Enums\AlertType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'type', 'severity', 'status', 'title', 'message', 'alertable_type',
    'alertable_id', 'metadata', 'triggered_at', 'acknowledged_at', 'resolved_at',
])]
class Alert extends Model
{
    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'severity' => AlertSeverity::class,
            'status' => AlertStatus::class,
            'metadata' => 'array',
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function alertable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Alert>  $query
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', AlertStatus::Open->value);
    }
}
