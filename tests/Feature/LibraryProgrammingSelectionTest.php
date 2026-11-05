<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Users\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LibraryProgrammingSelectionTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_business_selection_is_ordered_and_includes_media_outside_initial_picker_limit_without_saving(): void
    {
        $business = Business::factory()->create();
        $ownership = ['owner_type' => $business->getMorphClass(), 'owner_id' => $business->id];
        $old = MediaAsset::factory()->image()->create([...$ownership, 'created_at' => now()->subDay()]);
        $video = MediaAsset::factory()->video()->create($ownership);
        MediaAsset::factory()->count(100)->image()->create($ownership);
        $this->actingAs($this->businessUser($business))->get('/business/schedule?'.http_build_query(['media_ids' => [$video->id, $old->id]]))
            ->assertInertia(fn ($page) => $page->has('availableMedia', 100)->has('selectedMedia', 2)
                ->where('selectedMedia.0.id', $video->id)->where('selectedMedia.1.id', $old->id));
        $this->assertDatabaseCount('content_schedules', 0);
        $this->assertDatabaseCount('playlists', 0);
    }

    public function test_business_rejects_foreign_unready_missing_and_live_selection(): void
    {
        $business = Business::factory()->create();
        $ownership = ['owner_type' => $business->getMorphClass(), 'owner_id' => $business->id];
        $invalid = [MediaAsset::factory()->image()->create()->id, 999999];
        foreach (['pending', 'failed'] as $status) {
            $invalid[] = MediaAsset::factory()->image()->create([...$ownership, 'processing_status' => $status])->id;
        }
        $invalid[] = MediaAsset::factory()->create([...$ownership, 'type' => 'live_stream', 'metadata' => ['live' => ['original_url' => 'https://example.test/live']]])->id;
        $this->actingAs($this->businessUser($business));
        foreach ($invalid as $id) {
            $this->getJson('/business/schedule?'.http_build_query(['media_ids' => [$id]]))->assertUnprocessable()->assertJsonValidationErrors('media_ids');
        }
        $this->assertDatabaseCount('content_schedules', 0);
    }

    public function test_business_requires_editor_permissions_to_open_selected_content(): void
    {
        $business = Business::factory()->create();
        $media = MediaAsset::factory()->image()->create(['owner_type' => $business->getMorphClass(), 'owner_id' => $business->id]);
        $user = $this->businessUser($business);
        $user->roles()->firstOrFail()->permissions()->detach(Permission::query()->where('name', 'business.playlists.manage')->value('id'));
        $this->actingAs($user)->getJson('/business/schedule?'.http_build_query(['media_ids' => [$media->id]]))->assertForbidden();
        $this->get('/business/schedule')->assertOk();
    }

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
        $business = Business::factory()->create();
        $this->actingAs($this->businessUser($business));
        foreach (['invalid', [1, 1], [0], range(1, 101)] as $ids) {
            $this->getJson('/business/schedule?'.http_build_query(['media_ids' => $ids]))->assertUnprocessable();
        }
        $this->actingAs($this->superAdmin())->getJson('/admin/campaigns/create?'.http_build_query(['media_ids' => range(1, 31)]))->assertUnprocessable()->assertJsonValidationErrors('media_ids');
        $this->get('/admin/campaigns/create')->assertInertia(fn ($page) => $page->has('selectedMedia', 0));
    }
}
