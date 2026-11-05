<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LibraryProgrammingSelectionTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_admin_selection_preserves_order_and_creates_no_campaign(): void
    {
        $image = MediaAsset::factory()->image()->create();
        $video = MediaAsset::factory()->video()->create();
        $this->actingAs($this->superAdmin())->get('/admin/campaigns/create?'.http_build_query(['media_ids' => [$video->id, $image->id]]))
            ->assertInertia(fn ($page) => $page->where('campaign', null)->where('selectedMedia.0.id', $video->id)->where('selectedMedia.1.id', $image->id));
        $this->assertDatabaseCount('campaigns', 0);
        $this->assertDatabaseCount('campaign_creatives', 0);
    }

    public function test_admin_rejects_business_media_and_unready_and_live_sources(): void
    {
        $business = Business::factory()->create();
        $private = MediaAsset::factory()->image()->create(['owner_type' => $business->getMorphClass(), 'owner_id' => $business->id]);
        $pending = MediaAsset::factory()->image()->create(['processing_status' => 'pending']);
        $live = MediaAsset::factory()->create(['type' => 'live_stream', 'metadata' => ['live' => ['original_url' => 'https://example.test/live']]]);
        $this->actingAs($this->superAdmin());
        foreach ([$private->id, $pending->id, $live->id, 999999] as $id) {
            $this->getJson('/admin/campaigns/create?'.http_build_query(['media_ids' => [$id]]))->assertUnprocessable()->assertJsonValidationErrors('media_ids');
        }
    }

    public function test_selection_validates_shape_duplicates_and_existing_submit_limits(): void
    {
        $this->actingAs($this->superAdmin())->getJson('/admin/campaigns/create?'.http_build_query(['media_ids' => range(1, 31)]))->assertUnprocessable()->assertJsonValidationErrors('media_ids');
        $this->get('/admin/campaigns/create')->assertInertia(fn ($page) => $page->has('selectedMedia', 0));
    }
}
