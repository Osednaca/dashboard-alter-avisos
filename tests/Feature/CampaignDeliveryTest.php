<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Actions\PublishCampaign;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Jobs\RebuildDeviceManifests;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class CampaignDeliveryTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function campaign(Device $device): Campaign
    {
        $campaign = Campaign::factory()->create(['status' => CampaignStatus::Draft, 'starts_at' => today(), 'ends_at' => today()->addDays(3)]);
        $campaign->targets()->create(['target_type' => 'device', 'target_id' => $device->id, 'is_exclusion' => false]);
        $campaign->creatives()->create(['media_asset_id' => MediaAsset::factory()->image()->create()->id, 'duration' => 20, 'weight' => 1, 'position' => 0, 'status' => 'active']);

        return $campaign;
    }

    private function poll(Device $device): array
    {
        $this->withToken($device->issueToken());
        $this->getJson('/api/v1/device/sync')->assertOk();

        return $this->getJson('/api/v1/device/manifest')->assertOk()->json('manifest');
    }

    public function test_pause_publish_and_delete_reach_the_tv_without_a_queue_worker(): void
    {
        Bus::fake();
        $device = Device::factory()->online()->create();
        $a = $this->campaign($device);
        $b = $this->campaign($device);
        $publisher = app(PublishCampaign::class);
        $publisher->handle($a);
        $first = $this->poll($device);
        $this->assertSame([$a->id], array_column($first['payload']['advertising_playlist']['campaigns'], 'id'));
        $publisher->pause($a);
        $publisher->handle($b);
        $next = $this->poll($device);
        $this->assertGreaterThan((int) $first['version'], (int) $next['version']);
        $this->assertSame([$b->id], array_column($next['payload']['advertising_playlist']['campaigns'], 'id'));
        $this->actingAs($this->superAdmin())->delete("/admin/campaigns/{$b->id}")->assertRedirect();
        $this->assertSame([], $this->poll($device)['payload']['advertising_playlist']['campaigns']);
    }

    public function test_a_delayed_publication_job_cannot_revive_a_deleted_campaign(): void
    {
        Bus::fake();
        $device = Device::factory()->online()->create();
        $campaign = $this->campaign($device);
        app(PublishCampaign::class)->handle($campaign);
        $oldJob = new RebuildDeviceManifests([$device->id]);
        // There is already an undelivered manifest containing this campaign.
        app(BuildDeviceManifest::class)->handle($device);
        $this->actingAs($this->superAdmin())->delete("/admin/campaigns/{$campaign->id}")->assertRedirect();
        $oldJob->handle(app(BuildDeviceManifest::class));
        $this->assertSame([], $this->poll($device)['payload']['advertising_playlist']['campaigns']);
    }

    public function test_retargeting_an_active_campaign_removes_it_from_old_screens(): void
    {
        Bus::fake();
        $old = Device::factory()->online()->create();
        $new = Device::factory()->online()->create();
        $campaign = $this->campaign($old);
        app(PublishCampaign::class)->handle($campaign);
        $this->poll($old);
        $this->poll($new);
        $this->actingAs($this->superAdmin())->put("/admin/campaigns/{$campaign->id}", [
            'advertiser_id' => $campaign->advertiser_id, 'name' => $campaign->name, 'starts_at' => today()->toDateString(), 'ends_at' => today()->addDays(3)->toDateString(), 'priority' => 5,
            'creatives' => [['media_asset_id' => $campaign->creatives()->first()->media_asset_id, 'duration' => 60, 'weight' => 1]],
            'targets' => [['target_type' => 'device', 'target_id' => $new->id, 'is_exclusion' => false]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], $this->poll($old)['payload']['advertising_playlist']['campaigns']);
        $this->assertSame([$campaign->id], array_column($this->poll($new)['payload']['advertising_playlist']['campaigns'], 'id'));
    }

    public function test_scheduled_campaign_starts_and_expires_on_poll(): void
    {
        Bus::fake();
        $device = Device::factory()->online()->create();
        $campaign = $this->campaign($device);
        $campaign->update(['starts_at' => today()->addDay(), 'ends_at' => today()->addDay()]);
        app(PublishCampaign::class)->handle($campaign);
        $this->assertSame([], $this->poll($device)['payload']['advertising_playlist']['campaigns']);
        $this->travel(1)->days();
        $this->assertCount(1, $this->poll($device)['payload']['advertising_playlist']['campaigns']);
        $this->travel(1)->days();
        $this->assertSame([], $this->poll($device)['payload']['advertising_playlist']['campaigns']);
        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
    }

    public function test_failed_ack_of_an_old_download_does_not_drop_a_new_update(): void
    {
        $device = Device::factory()->online()->create();
        $a = app(BuildDeviceManifest::class)->handle($device);
        $b = app(BuildDeviceManifest::class)->handle($device);
        $this->withToken($device->issueToken())->postJson('/api/v1/device/sync/acknowledge', ['version' => $a->version, 'success' => false])->assertOk();
        $this->assertSame($b->version, $device->fresh()->pending_manifest_version);
        $this->assertSame($b->version, $this->getJson('/api/v1/device/manifest')->assertOk()->json('manifest.version'));
    }
}
