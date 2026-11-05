<?php

namespace App\Domain\QuickPlay\Actions;

use App\Domain\Devices\Enums\DeviceCommandStatus;
use App\Domain\QuickPlay\Enums\QuickPlayDeviceStatus;
use App\Domain\QuickPlay\Events\QuickPlayStatusUpdated;
use App\Domain\QuickPlay\Models\QuickPlay;
use App\Domain\QuickPlay\Models\QuickPlayDevice;
use Illuminate\Support\Facades\DB;

class UpdateQuickPlayDeviceStatus
{
    /**
     * Apply a delivery status reported by a device (or by the expiry sweeper)
     * and keep the command + aggregate counters in sync.
     */
    public function handle(QuickPlayDevice $row, QuickPlayDeviceStatus $status, ?string $error = null): QuickPlayDevice
    {
        return DB::transaction(function () use ($row, $status, $error) {
            $quickPlay = QuickPlay::withTrashed()->lockForUpdate()->findOrFail($row->quick_play_id);
            $row->refresh();
            // Old callbacks must not revive a deleted play or regress a finished attempt.
            if ($quickPlay->trashed() || $row->status->isTerminal()) {
                return $row;
            }
            $attributes = [
                'status' => $status,
                'error' => $status === QuickPlayDeviceStatus::Failed ? ($error ?? $row->error) : null,
            ];

            if ($status === QuickPlayDeviceStatus::Playing && $row->started_at === null) {
                $attributes['started_at'] = now();
            }

            if ($status->isTerminal()) {
                $attributes['completed_at'] = now();
            }

            $row->forceFill($attributes)->save();

            if ($row->command) {
                $row->command->forceFill([
                    'status' => match ($status) {
                        QuickPlayDeviceStatus::Completed => DeviceCommandStatus::Completed,
                        QuickPlayDeviceStatus::Failed => DeviceCommandStatus::Failed,
                        default => DeviceCommandStatus::Sent,
                    },
                    'executed_at' => $status->isTerminal() ? now() : $row->command->executed_at,
                    'error' => $status === QuickPlayDeviceStatus::Failed ? $attributes['error'] : null,
                ])->save();
            }

            $quickPlay->refreshProgress();

            QuickPlayStatusUpdated::dispatch($quickPlay->load('mediaAsset'));

            return $row;
        });
    }
}
