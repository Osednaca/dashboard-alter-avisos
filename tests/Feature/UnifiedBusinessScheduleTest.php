<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class UnifiedBusinessScheduleTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_removed_programming_endpoints_cannot_read_or_mutate_legacy_content(): void
    {
        $business = Business::factory()->create();
        $media = MediaAsset::factory()->image()->create(['owner_type' => $business->getMorphClass(), 'owner_id' => $business->id]);
        $playlist = $business->playlists()->create(['name' => 'Original', 'type' => 'business', 'status' => 'active']);
        $schedule = $business->schedules()->create(['name' => 'Original', 'playlist_id' => $playlist->id, 'status' => 'active']);
        $this->actingAs($this->businessUser($business));
        $routes = [
            ['GET', '/business/library'], ['POST', '/business/library'],
            ['PUT', "/business/library/{$media->id}"], ['DELETE', "/business/library/{$media->id}"],
            ['GET', '/business/schedule'], ['POST', '/business/schedule'],
            ['GET', '/business/schedule/media'], ['GET', '/business/schedule/media-status'],
            ['PUT', "/business/schedule/{$schedule->id}"], ['DELETE', "/business/schedule/{$schedule->id}"],
            ['GET', '/business/playlists'], ['POST', '/business/playlists'],
            ['GET', "/business/playlists/{$playlist->id}"], ['PUT', "/business/playlists/{$playlist->id}"],
            ['DELETE', "/business/playlists/{$playlist->id}"], ['POST', "/business/playlists/{$playlist->id}/items"],
            ['PUT', "/business/playlists/{$playlist->id}/items/reorder"],
            ['PUT', "/business/playlists/{$playlist->id}/items/1"], ['DELETE', "/business/playlists/{$playlist->id}/items/1"],
        ];
        foreach ($routes as [$method, $uri]) {
            $this->call($method, $uri, ['name' => 'Changed'])->assertNotFound();
        }
        $this->assertDatabaseHas('media_assets', ['id' => $media->id]);
        $this->assertDatabaseHas('playlists', ['id' => $playlist->id, 'name' => 'Original']);
        $this->assertDatabaseHas('content_schedules', ['id' => $schedule->id, 'name' => 'Original']);
    }
}
