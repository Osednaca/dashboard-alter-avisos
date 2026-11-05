<?php

namespace App\Domain\Campaigns\Events;

use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignPublished implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Campaign $campaign) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('network')];
    }

    public function broadcastAs(): string
    {
        return 'CampaignPublished';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'name' => $this->campaign->name,
            'status' => $this->campaign->status->value,
            'target_screen_count' => $this->campaign->target_screen_count,
            'at' => now()->toIso8601String(),
        ];
    }
}
