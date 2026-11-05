<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Actions\PublishCampaign;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class CampaignAutomaticSyncTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private array $tokens = [];

    private function screen(): Device
    {
        $device = Device::factory()->online()->create();
        $this->tokens[$device->id] = $device->issueToken();

        return $device;
    }

    private function authenticateScreen(Device $device): void
    {
        $this->withToken($this->tokens[$device->id]);
    }

    private function campaign(array $screens): Campaign
    {
        $campaign = Campaign::factory()->create([
            'status' => 'draft', 'starts_at' => today(), 'ends_at' => today()->addWeek(),
        ]);
        $campaign->creatives()->create([
            'media_asset_id' => MediaAsset::factory()->image()->create()->id,
            'duration' => 20, 'weight' => 1, 'position' => 0, 'status' => 'active',
        ]);
        foreach ($screens as $screen) {
            $campaign->targets()->create(['target_type' => 'device', 'target_id' => $screen->id]);
        }
        app(PublishCampaign::class)->handle($campaign);

        return $campaign;
    }

    private function edit(Campaign $campaign, array $screens, int $duration): void
    {
        $this->actingAs($this->superAdmin())->put("/admin/campaigns/{$campaign->id}", [
            'advertiser_id' => $campaign->advertiser_id,
            'name' => $campaign->name,
            'starts_at' => today()->toDateString(), 'ends_at' => today()->addWeek()->toDateString(),
            'priority' => 5,
            'creatives' => [[
                'media_asset_id' => $campaign->creatives()->first()->media_asset_id,
                'duration' => $duration, 'weight' => 1,
            ]],
            'targets' => array_map(fn ($screen) => [
                'target_type' => 'device', 'target_id' => $screen->id, 'is_exclusion' => false,
            ], $screens),
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    /** Simulate the device's regular status poll and manifest download; never queue a SYNC command. */
    private function poll(Device $screen): array
    {
        $this->authenticateScreen($screen);
        $status = $this->getJson('/api/v1/device/sync')->assertOk();
        $manifest = $this->getJson('/api/v1/device/manifest')->assertOk()->json('manifest');
        $this->assertSame($manifest['version'], $status->json('pending_manifest_version') ?? $status->json('current_manifest_version'));

        return $manifest;
    }

    private function acknowledge(Device $screen, array $manifest, bool $success = true): void
    {
        $this->authenticateScreen($screen);
        $this->postJson('/api/v1/device/sync/acknowledge', [
            'version' => $manifest['version'], 'success' => $success,
        ])->assertOk()->assertJsonPath('ok', $success);
    }

    private function assertDuration(array $manifest, int $duration): void
    {
        $this->assertSame($duration, $manifest['payload']['advertising_playlist']['campaigns'][0]['creatives'][0]['duration']);
    }

    public function test_edits_reach_every_assigned_screen_without_jobs_or_manual_sync_and_late_ack_cannot_erase_them(): void
    {
        Bus::fake();
        $this->freezeTime();
        [$first, $second, $unrelated] = [$this->screen(), $this->screen(), $this->screen()];
        $campaign = $this->campaign([$first, $second]);
        $initial = [];
        foreach ([$first, $second, $unrelated] as $screen) {
            $initial[$screen->id] = $this->poll($screen);
            $this->acknowledge($screen, $initial[$screen->id]);
        }

        $this->edit($campaign, [$first, $second], 45);
        $downloads = [];
        foreach ([$first, $second] as $screen) {
            $downloads[$screen->id] = $this->poll($screen);
            $this->assertDuration($downloads[$screen->id], 45);
            $this->assertNotSame($initial[$screen->id]['checksum'], $downloads[$screen->id]['checksum']);
        }
        $this->assertSame($initial[$unrelated->id]['version'], $this->poll($unrelated)['version']);

        // Both devices are downloading the first edit when the administrator edits again.
        $this->edit($campaign, [$first, $second], 60);
        foreach ([$first, $second] as $screen) {
            $latest = $this->poll($screen);
            $this->assertDuration($latest, 60);
            $this->assertGreaterThan((int) $downloads[$screen->id]['version'], (int) $latest['version']);
            $this->acknowledge($screen, $downloads[$screen->id]);
            $this->assertSame($latest['version'], $this->poll($screen)['version']);
            $this->acknowledge($screen, $latest);
            // A lost ACK response may cause the device to retry the same acknowledgement.
            $this->acknowledge($screen, $latest);
            $this->assertSame($latest['version'], $screen->fresh()->current_manifest_version);
            $this->assertNull($screen->fresh()->pending_manifest_version);
            $this->assertSame(1, $screen->manifests()->where('status', 'current')->count());
        }
        $this->assertDatabaseCount('device_commands', 0);
    }

    public function test_retargeting_and_failed_download_reconcile_on_the_next_poll_without_a_worker(): void
    {
        Bus::fake();
        [$first, $removed, $added] = [$this->screen(), $this->screen(), $this->screen()];
        $campaign = $this->campaign([$first, $removed]);
        foreach ([$first, $removed, $added] as $screen) {
            $this->acknowledge($screen, $this->poll($screen));
        }
        $oldVersion = $first->fresh()->current_manifest_version;

        $this->edit($campaign, [$first, $added], 90);
        $failed = $this->poll($first);
        $this->acknowledge($first, $failed, false);
        $this->assertSame($oldVersion, $first->fresh()->current_manifest_version);
        $retry = $this->poll($first);
        $this->assertDuration($retry, 90);
        $this->assertGreaterThan((int) $failed['version'], (int) $retry['version']);
        $this->acknowledge($first, $retry);

        $removedManifest = $this->poll($removed);
        $this->assertSame([], $removedManifest['payload']['advertising_playlist']['campaigns']);
        $this->acknowledge($removed, $removedManifest);
        $addedManifest = $this->poll($added);
        $this->assertDuration($addedManifest, 90);
        $this->acknowledge($added, $addedManifest);
        $this->assertDatabaseCount('device_commands', 0);
    }
}
