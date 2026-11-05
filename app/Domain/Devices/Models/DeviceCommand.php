<?php

namespace App\Domain\Devices\Models;

use App\Domain\Devices\Enums\DeviceCommandStatus;
use App\Domain\Devices\Enums\DeviceCommandType;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'device_id', 'command', 'payload', 'status', 'created_by',
    'sent_at', 'executed_at', 'result', 'error', 'expires_at',
])]
class DeviceCommand extends Model
{
    protected function casts(): array
    {
        return [
            'command' => DeviceCommandType::class,
            'status' => DeviceCommandStatus::class,
            'payload' => 'array',
            'result' => 'array',
            'sent_at' => 'datetime',
            'executed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<DeviceCommand>  $query
     */
    public function scopeDeliverable(Builder $query): Builder
    {
        // Sending an HTTP response is not a receipt acknowledgement. The TV
        // persists command IDs and ignores repeats, so retry the same command
        // until its result arrives without extending its original lifetime.
        return $query->where(fn (Builder $q) => $q
            ->where('status', DeviceCommandStatus::Pending->value)
            ->orWhere(fn (Builder $retry) => $retry
                ->where('status', DeviceCommandStatus::Sent->value)
                ->where(fn (Builder $sent) => $sent
                    ->whereNull('sent_at')
                    ->orWhere('sent_at', '<=', now()->subMinute()))))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
