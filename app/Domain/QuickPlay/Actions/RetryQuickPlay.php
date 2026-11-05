<?php

namespace App\Domain\QuickPlay\Actions;

use App\Domain\Businesses\Models\Business;
use App\Domain\QuickPlay\Enums\QuickPlayDeviceStatus;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use App\Domain\QuickPlay\Models\QuickPlay;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetryQuickPlay
{
    public function __construct(private StartQuickPlay $start) {}

    public function handle(QuickPlay $source, User $user): QuickPlay
    {
        return DB::transaction(function () use ($source, $user) {
            $source = QuickPlay::query()->lockForUpdate()->findOrFail($source->id);
            // A double click or HTTP retry must not enqueue a second playback.
            if ($retryId = $source->metadata['retry_id'] ?? null) {
                return QuickPlay::query()->find($retryId)
                    ?? throw ValidationException::withMessages(['quick_play' => 'El reintento ya fue eliminado. Crea una nueva reproducción.']);
            }
            if (! $source->canRetry()) {
                throw ValidationException::withMessages(['quick_play' => 'Solo puedes reintentar un envío finalizado con pantallas fallidas.']);
            }
            $media = $source->mediaAsset;
            if (! $media) {
                throw ValidationException::withMessages(['quick_play' => 'El archivo de este envío ya no está disponible.']);
            }
            $failedIds = $source->devices()->where('status', QuickPlayDeviceStatus::Failed->value)->pluck('device_id')->all();
            $retry = $this->start->handle(
                $user, $media, $source->display_mode, QuickPlayScope::Devices, $source->duration,
                ['device_ids' => $failedIds],
                $source->business_id ? Business::query()->findOrFail($source->business_id) : null,
            );
            $retry->forceFill(['metadata' => [...($retry->metadata ?? []), 'retry_of_id' => $source->id]])->save();
            $source->forceFill(['metadata' => [...($source->metadata ?? []), 'retry_id' => $retry->id]])->save();

            return $retry;
        });
    }
}
