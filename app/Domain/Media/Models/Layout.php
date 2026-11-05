<?php

namespace App\Domain\Media\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Media\Enums\LayoutOrientation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'orientation', 'business_percentage', 'advertising_percentage',
    'configuration', 'is_default',
])]
class Layout extends Model
{
    protected function casts(): array
    {
        return [
            'orientation' => LayoutOrientation::class,
            'configuration' => 'array',
            'is_default' => 'boolean',
            'business_percentage' => 'integer',
            'advertising_percentage' => 'integer',
        ];
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'current_layout_id');
    }

    public function ratioLabel(): string
    {
        return "{$this->business_percentage} / {$this->advertising_percentage}";
    }

    /**
     * @param  Builder<Layout>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where('name', 'like', "%{$term}%"));
    }
}
