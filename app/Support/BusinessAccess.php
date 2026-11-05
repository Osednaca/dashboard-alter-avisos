<?php

namespace App\Support;

use App\Domain\Businesses\Models\Business;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the business the current request belongs to and provides ownership
 * checks used to prevent IDOR between businesses.
 */
class BusinessAccess
{
    public static function current(): ?Business
    {
        $business = request()?->attributes->get('current_business');

        if ($business instanceof Business) {
            return $business;
        }

        if (app()->bound('current_business')) {
            $bound = app('current_business');

            return $bound instanceof Business ? $bound : null;
        }

        return null;
    }

    public static function set(Business $business): void
    {
        app()->instance('current_business', $business);
    }

    public static function id(): ?int
    {
        return self::current()?->id;
    }

    /**
     * True when the model belongs to the current business.
     */
    public static function owns(?Model $model, string $foreignKey = 'business_id'): bool
    {
        $business = self::current();

        return $business !== null
            && $model !== null
            && (int) $model->getAttribute($foreignKey) === (int) $business->id;
    }

    /**
     * True when the media asset is owned by the current business.
     */
    public static function ownsMedia(?MediaAsset $media): bool
    {
        $business = self::current();

        return $business !== null
            && $media !== null
            && $media->owner_type === $business->getMorphClass()
            && (int) $media->owner_id === (int) $business->id;
    }
}
