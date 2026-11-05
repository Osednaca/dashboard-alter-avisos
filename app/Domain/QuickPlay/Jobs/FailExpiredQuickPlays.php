<?php

namespace App\Domain\QuickPlay\Jobs;

use App\Domain\QuickPlay\Actions\UpdateQuickPlayDeviceStatus;
use App\Domain\QuickPlay\Enums\QuickPlayDeviceStatus;
use App\Domain\QuickPlay\Enums\QuickPlayStatus;
use App\Domain\QuickPlay\Models\QuickPlay;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FailExpiredQuickPlays implements ShouldQueue
{
    use Queueable;

    /**
     * Any quick play still in flight past its expiry is marked as failed so the
     * operator sees a clear outcome instead of a permanently "sending" entry.
     */
    public function handle(UpdateQuickPlayDeviceStatus $updateStatus): void
    {
        QuickPlay::query()
            ->where('status', QuickPlayStatus::Sending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('devices')
            ->chunkById(50, function ($quickPlays) use ($updateStatus) {
                foreach ($quickPlays as $quickPlay) {
                    foreach ($quickPlay->devices as $row) {
                        if ($row->status->isTerminal()) {
                            continue;
                        }

                        $updateStatus->handle(
                            $row,
                            QuickPlayDeviceStatus::Failed,
                            'Tiempo de espera agotado sin confirmación de la pantalla.',
                        );
                    }

                    $quickPlay->refreshProgress();
                }
            });
    }
}
