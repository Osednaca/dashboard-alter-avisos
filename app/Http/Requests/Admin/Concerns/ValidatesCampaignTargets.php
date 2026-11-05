<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Domain\Campaigns\Enums\CampaignTargetType;
use App\Domain\Locations\Models\City;

trait ValidatesCampaignTargets
{
    protected function validateTargets($validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        foreach ($this->input('targets', []) as $index => $target) {
            $type = CampaignTargetType::from($target['target_type']);
            if ($type === CampaignTargetType::City && isset($target['target_id'])) {
                if (! empty($target['target_value']) || ! City::query()->where('status', 'active')->whereKey($target['target_id'])->exists()) {
                    $validator->errors()->add("targets.{$index}.target_id", 'Selecciona una ciudad activa del catálogo.');
                }

                continue;
            }
            if ($type->isEntity() && empty($target['target_id'])) {
                $validator->errors()->add("targets.{$index}.target_id", 'Selecciona un objetivo válido.');
            }
            if (! $type->isEntity() && empty($target['target_value'])) {
                $validator->errors()->add("targets.{$index}.target_value", 'Selecciona un valor para segmentar.');
            }
        }
    }
}
