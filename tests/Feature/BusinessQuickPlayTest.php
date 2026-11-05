<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BusinessQuickPlayTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_legacy_quick_play_endpoints_are_unavailable_and_cannot_send_commands(): void
    {
        $business = Business::factory()->create();
        $media = MediaAsset::factory()->image()->create(['owner_type' => $business->getMorphClass(), 'owner_id' => $business->id]);
        $this->actingAs($this->businessUser($business));
        foreach ([['GET', '/business/quick-play'], ['GET', '/business/quick-play/create'],
            ['POST', '/business/quick-play'], ['GET', '/business/quick-play/1'],
            ['POST', '/business/quick-play/1/retry'], ['DELETE', '/business/quick-play/1']] as [$method, $uri]) {
            $this->call($method, $uri, ['media_asset_id' => $media->id])->assertNotFound();
        }
        $this->assertDatabaseCount('quick_plays', 0);
        $this->assertDatabaseCount('device_commands', 0);
    }

    public function test_admin_quick_play_offers_only_fullscreen(): void
    {
        $this->actingAs($this->superAdmin())->get('/admin/quick-play/create')->assertOk()
            ->assertInertia(fn ($page) => $page->has('options.displayModes', 1)->where('options.displayModes.0.value', 'fullscreen'));
    }
}
