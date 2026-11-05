<?php

namespace App\Http\Controllers\Business\Concerns;

use App\Domain\Businesses\Models\Business;
use App\Domain\Media\Models\MediaAsset;
use App\Support\BusinessAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesBusiness
{
    protected function business(): Business
    {
        $business = BusinessAccess::current();

        abort_unless($business instanceof Business, 403, 'Contexto de negocio no disponible.');

        return $business;
    }

    protected function businessId(): int
    {
        return $this->business()->id;
    }

    /**
     * Abort with 404 when a resource does not belong to the current business.
     */
    protected function authorizeOwned(?Model $model, string $foreignKey = 'business_id'): void
    {
        abort_unless($model !== null && BusinessAccess::owns($model, $foreignKey), 404);
    }

    protected function authorizeOwnedMedia(?MediaAsset $media): void
    {
        abort_unless($media !== null && BusinessAccess::ownsMedia($media), 404);
    }

    /**
     * A query constrained to business-owned media assets.
     *
     * @return Builder<MediaAsset>
     */
    protected function businessMediaQuery(): Builder
    {
        return MediaAsset::query()
            ->where('owner_type', $this->business()->getMorphClass())
            ->where('owner_id', $this->businessId());
    }
}
