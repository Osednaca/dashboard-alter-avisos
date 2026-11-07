<?php

namespace App\Domain\Campaigns\Support;

use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignTarget;
use Illuminate\Database\Eloquent\Builder;

/** Defines which existing targeting rules can be represented by the current editor. */
class EditableCampaignTargets
{
    public function businesses(): Builder
    {
        return Business::query()->where('slug', '!=', Business::INTERNAL_SLUG);
    }

    public function hasHidden(Campaign $campaign): bool
    {
        return $campaign->targets()->where(function ($q) {
            $q->whereNotIn('target_type', ['city', 'device', 'business'])
                ->orWhere(function ($q) {
                    $q->where('target_type', 'business')->where(function ($q) {
                        $q->whereNull('target_id')->orWhereNotIn('target_id', $this->businesses()->select('id'))
                            ->orWhere(fn ($q) => $q->whereNotNull('target_value')->where('target_value', '!=', ''));
                    });
                });
        })->exists();
    }

    public function isEditable(CampaignTarget $target, array $businessIds): bool
    {
        return in_array($target->target_type->value, ['city', 'device'], true)
            || ($target->target_type->value === 'business' && in_array($target->target_id, $businessIds, true)
                && ($target->target_value === null || $target->target_value === ''));
    }

    /** Prevent hidden exclusions from turning a targeted campaign into a network-wide one. */
    public function wouldExpandWithoutVisibleInclusions(Campaign $campaign, array $newTargets): bool
    {
        $targets = $campaign->targets()->get();
        $businessIds = $this->businesses()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $hidden = $targets->filter(fn ($target) => ! $this->isEditable($target, $businessIds));
        $previouslyIncluded = $targets->contains(fn ($target) => ! $target->is_exclusion);
        $hiddenIncludes = $hidden->contains(fn ($target) => ! $target->is_exclusion);
        $newIncludes = collect($newTargets)->contains(fn ($target) => ! (bool) ($target['is_exclusion'] ?? false));

        return $previouslyIncluded && $hidden->isNotEmpty() && ! $hiddenIncludes && ! $newIncludes;
    }

    public function deleteEditable(Campaign $campaign): void
    {
        $campaign->targets()->where(function ($q) {
            $q->whereIn('target_type', ['city', 'device'])
                ->orWhere(fn ($q) => $q->where('target_type', 'business')
                    ->whereIn('target_id', $this->businesses()->select('id'))
                    ->where(fn ($q) => $q->whereNull('target_value')->orWhere('target_value', '')));
        })->delete();
    }
}
