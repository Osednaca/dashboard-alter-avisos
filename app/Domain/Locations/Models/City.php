<?php

namespace App\Domain\Locations\Models;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Enums\LocationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'state', 'country', 'timezone', 'status'])]
class City extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['status' => LocationStatus::class];
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'city_business')->withTimestamps();
    }

    public function devices(): Builder
    {
        return Device::query()->whereIn('business_id', $this->businesses()->select('businesses.id'));
    }
}
