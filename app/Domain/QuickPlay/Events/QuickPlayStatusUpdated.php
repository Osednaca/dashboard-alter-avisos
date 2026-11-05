<?php

namespace App\Domain\QuickPlay\Events;

use App\Domain\QuickPlay\Models\QuickPlay;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuickPlayStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public QuickPlay $quickPlay) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('quick-plays.'.$this->quickPlay->id),
            new PrivateChannel('network'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'QuickPlayStatusUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'quick_play_id' => $this->quickPlay->id,
            'status' => $this->quickPlay->status->value,
            'targets_count' => $this->quickPlay->targets_count,
            'delivered_count' => $this->quickPlay->delivered_count,
            'failed_count' => $this->quickPlay->failed_count,
            'at' => now()->toIso8601String(),
        ];
    }
}
