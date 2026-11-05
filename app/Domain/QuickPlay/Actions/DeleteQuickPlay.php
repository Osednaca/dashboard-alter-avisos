<?php

namespace App\Domain\QuickPlay\Actions;

use App\Domain\Devices\Enums\DeviceCommandStatus;
use App\Domain\Devices\Models\DeviceCommand;
use App\Domain\QuickPlay\Models\QuickPlay;
use Illuminate\Support\Facades\DB;

class DeleteQuickPlay
{
    public function handle(QuickPlay $quickPlay): void
    {
        DB::transaction(function () use ($quickPlay) {
            $quickPlay = QuickPlay::query()->lockForUpdate()->findOrFail($quickPlay->id);
            DeviceCommand::query()->whereIn('id', $quickPlay->devices()->select('command_id'))
                ->whereIn('status', [DeviceCommandStatus::Pending->value, DeviceCommandStatus::Sent->value])
                ->update([
                    'status' => DeviceCommandStatus::Failed->value,
                    'error' => 'Instant Play eliminado por el usuario.',
                    'executed_at' => now(),
                    'expires_at' => now(),
                ]);
            // Keep the delivery ledger so TVs can acknowledge commands already received.
            $quickPlay->delete();
        });
    }
}
