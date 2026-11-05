<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Scheduling\Jobs\RefreshBusinessManifests;
use App\Domain\Scheduling\Models\ContentSchedule;
use App\Domain\Scheduling\Services\ResolveActivePlaylist;
use App\Domain\Users\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class UnifiedBusinessScheduleTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function scenario(): array
    {
        Queue::fake();
        $business = Business::factory()->create(['timezone' => 'America/Bogota']);
        $user = $this->businessUser($business);
        $media = MediaAsset::factory()->image()->create([
            'owner_type' => $business->getMorphClass(), 'owner_id' => $business->id,
        ]);

        return [$business, $user, $media];
    }

    protected function payload(MediaAsset $media): array
    {
        return [
            'name' => 'Menú almuerzo', 'status' => 'active', 'priority' => 17,
            'daily_start_time' => '11:00', 'daily_end_time' => '15:00', 'days_of_week' => [1, 2, 3, 4, 5],
            'items' => [
                ['media_asset_id' => $media->id, 'duration_seconds' => 12, 'transition' => 'slide_left'],
                ['media_asset_id' => $media->id, 'duration_seconds' => 30, 'transition' => 'fade'],
            ],
        ];
    }

    public function test_one_save_creates_owned_content_in_order_and_hydrates_every_field(): void
    {
        [$business, $user, $media] = $this->scenario();
        $this->actingAs($user)->post('/business/schedule', $this->payload($media))->assertSessionHasNoErrors();
        $schedule = ContentSchedule::query()->firstOrFail();
        $this->assertTrue($schedule->playlist->is_schedule_managed);
        $this->assertSame($business->id, $schedule->playlist->business_id);
        $this->assertSame([12, 30], $schedule->playlist->items->pluck('duration')->all());
        $this->assertSame([0, 1], $schedule->playlist->items->pluck('sort_order')->all());
        $this->get('/business/schedule')->assertInertia(fn ($page) => $page
            ->component('Business/Schedule/Index')->where('schedules.0.priority', 17)
            ->where('schedules.0.items.0.media.id', $media->id)
            ->where('schedules.0.items.0.transition', 'slide_left')->has('transitions', 6));
        Queue::assertPushed(RefreshBusinessManifests::class, fn ($job) => $job->businessId === $business->id && $job->locationId === null);
    }

    public function test_unified_edit_copies_legacy_shared_content_and_keeps_fallback_untouched(): void
    {
        [$business, $user, $media] = $this->scenario();
        $legacy = $business->playlists()->create(['name' => 'Compartida', 'type' => 'business', 'status' => 'active']);
        $legacy->items()->create(['media_asset_id' => $media->id, 'duration' => 9, 'transition' => 'none', 'sort_order' => 0]);
        $first = $business->schedules()->create(['playlist_id' => $legacy->id, 'name' => 'A', 'status' => 'active']);
        $second = $business->schedules()->create(['playlist_id' => $legacy->id, 'name' => 'B', 'status' => 'active']);
        $device = Device::factory()->create(['business_id' => $business->id, 'current_playlist_id' => $legacy->id]);
        $this->actingAs($user)->put('/business/schedule/'.$first->id, $this->payload($media))->assertSessionHasNoErrors();
        $this->assertNotSame($legacy->id, $first->fresh()->playlist_id);
        $this->assertSame($legacy->id, $second->fresh()->playlist_id);
        $this->assertSame(9, $legacy->fresh()->items->first()->duration);
        $this->assertSame($legacy->id, $device->fresh()->current_playlist_id);
        $managedId = $first->fresh()->playlist_id;
        $this->put('/business/schedule/'.$first->id, $this->payload($media))->assertSessionHasNoErrors();
        $this->assertSame($managedId, $first->fresh()->playlist_id);
        $this->assertSame(2, $first->fresh()->playlist->items()->count());
    }

    public function test_invalid_or_foreign_or_unready_items_never_partially_save(): void
    {
        [$business, $user, $media] = $this->scenario();
        $foreign = MediaAsset::factory()->create();
        $this->actingAs($user);
        foreach ([$foreign, $media->replicate()->fill(['processing_status' => 'pending']), $media->replicate()->fill(['processing_status' => 'failed'])] as $invalid) {
            if (! $invalid->exists) {
                $invalid->save();
            }
            $payload = $this->payload($media);
            $payload['items'][1]['media_asset_id'] = $invalid->id;
            $this->postJson('/business/schedule', $payload)->assertUnprocessable()->assertJsonValidationErrors('items.1.media_asset_id');
            $this->assertDatabaseCount('content_schedules', 0);
            $this->assertDatabaseCount('playlists', 0);
        }
        $payload = $this->payload($media);
        $payload['items'][0]['transition'] = 'arbitrary';
        $this->postJson('/business/schedule', $payload)->assertUnprocessable();
        Queue::assertNothingPushed();
    }

    public function test_managed_content_cannot_be_shared_or_mutated_through_legacy_apis(): void
    {
        [$business, $user, $media] = $this->scenario();
        $this->actingAs($user)->post('/business/schedule', $this->payload($media))->assertSessionHasNoErrors();
        $playlist = ContentSchedule::query()->firstOrFail()->playlist;
        $legacyPayload = $this->payload($media);
        unset($legacyPayload['items']);
        $legacyPayload['playlist_id'] = $playlist->id;
        $this->postJson('/business/schedule', $legacyPayload)->assertUnprocessable();
        $this->put('/business/playlists/'.$playlist->id, ['name' => 'Cambio'])->assertNotFound();
        $this->delete('/business/playlists/'.$playlist->id)->assertNotFound();
        $this->post('/business/playlists/'.$playlist->id.'/items', ['media_asset_id' => $media->id, 'duration' => 10, 'transition' => 'none'])->assertNotFound();
        $this->assertDatabaseCount('content_schedules', 1);
        $this->assertSame(2, $playlist->items()->count());
    }

    public function test_managed_playlists_are_not_fallback_after_window_pause_or_delete(): void
    {
        [$business, $user, $media] = $this->scenario();
        $this->actingAs($user)->post('/business/schedule', $this->payload($media))->assertSessionHasNoErrors();
        $schedule = ContentSchedule::query()->firstOrFail();
        $device = Device::factory()->create(['business_id' => $business->id, 'current_playlist_id' => $schedule->playlist_id]);
        $resolver = app(ResolveActivePlaylist::class);
        $inside = Carbon::parse('2026-09-28 12:00', 'America/Bogota');
        $this->assertSame($schedule->playlist_id, $resolver->forDevice($device, $inside)?->id);
        $this->assertNull($resolver->forDevice($device, $inside->copy()->hour(20)));
        $schedule->update(['status' => 'inactive']);
        $this->assertNull($resolver->forDevice($device, $inside));
        $this->delete('/business/schedule/'.$schedule->id)->assertRedirect();
        $this->assertNull($resolver->forDevice($device->fresh(), $inside));
        $this->assertDatabaseHas('media_assets', ['id' => $media->id]);
    }

    public function test_status_polling_never_exposes_foreign_assets_and_legacy_links_redirect(): void
    {
        [$business, $user, $media] = $this->scenario();
        $foreign = MediaAsset::factory()->create();
        $this->actingAs($user)->getJson('/business/schedule/media-status?ids[]='.$media->id.'&ids[]='.$foreign->id)
            ->assertOk()->assertJsonCount(1, 'media')->assertJsonPath('media.0.id', $media->id);
        $this->get('/business/playlists')->assertRedirect('/business/schedule');
        $legacy = $business->playlists()->create(['name' => 'Anterior', 'type' => 'business', 'status' => 'active']);
        $this->get('/business/playlists/'.$legacy->id)->assertRedirect('/business/schedule?import='.$legacy->id);
    }

    public function test_invalid_edit_preserves_contents_and_moving_location_refreshes_every_screen(): void
    {
        [$business, $user, $media] = $this->scenario();
        $old = Location::factory()->create(['business_id' => $business->id]);
        $new = Location::factory()->create(['business_id' => $business->id]);
        $payload = $this->payload($media) + ['location_id' => $old->id];
        $this->actingAs($user)->post('/business/schedule', $payload)->assertSessionHasNoErrors();
        $schedule = ContentSchedule::query()->firstOrFail();
        $itemIds = $schedule->playlist->items->pluck('id')->all();
        $payload['items'][1]['duration_seconds'] = -1;
        $this->putJson('/business/schedule/'.$schedule->id, $payload)->assertUnprocessable();
        $this->assertSame($itemIds, $schedule->fresh()->playlist->items->pluck('id')->all());
        $payload = $this->payload($media) + ['location_id' => $new->id];
        $payload['daily_start_time'] = null;
        $payload['daily_end_time'] = null;
        $payload['items'] = array_reverse($payload['items']);
        $this->put('/business/schedule/'.$schedule->id, $payload)->assertSessionHasNoErrors();
        $this->assertSame($new->id, $schedule->fresh()->location_id);
        $this->assertNull($schedule->fresh()->daily_start_time);
        $this->assertSame([30, 12], $schedule->fresh()->playlist->items->pluck('duration')->all());
        Queue::assertPushed(RefreshBusinessManifests::class, 2);
        Queue::assertNotPushed(RefreshBusinessManifests::class, fn ($job) => $job->locationId !== null);
    }

    public function test_unified_save_does_not_expand_playlist_or_upload_permissions(): void
    {
        [$business, $user, $media] = $this->scenario();
        $role = $user->roles()->firstOrFail();
        $role->permissions()->detach(Permission::query()->where('name', 'business.playlists.manage')->value('id'));
        $this->actingAs($user)->postJson('/business/schedule', $this->payload($media))->assertForbidden();
        $this->assertDatabaseCount('content_schedules', 0);
        $role->permissions()->detach(Permission::query()->where('name', 'business.media.view')->value('id'));
        $this->getJson('/business/schedule/media-status?ids[]='.$media->id)->assertForbidden();
        $this->get('/business/schedule')->assertInertia(fn ($page) => $page->has('availableMedia', 0)->has('legacyPlaylists', 0));
        $role->permissions()->detach(Permission::query()->where('name', 'business.media.upload')->value('id'));
        $this->postJson('/business/library', [])->assertForbidden();
    }

    public function test_other_business_locations_and_schedules_and_advertiser_playlists_are_rejected(): void
    {
        [$business, $user, $media] = $this->scenario();
        $foreignBusiness = Business::factory()->create();
        $location = Location::factory()->create(['business_id' => $foreignBusiness->id]);
        $this->actingAs($user)->postJson('/business/schedule', $this->payload($media) + ['location_id' => $location->id])
            ->assertUnprocessable()->assertJsonValidationErrors('location_id');
        $playlist = $foreignBusiness->playlists()->create(['name' => 'Foreign', 'type' => 'business', 'status' => 'active']);
        $schedule = $foreignBusiness->schedules()->create(['name' => 'Foreign', 'playlist_id' => $playlist->id, 'status' => 'active']);
        $this->putJson('/business/schedule/'.$schedule->id, $this->payload($media))->assertNotFound();
        $this->delete('/business/schedule/'.$schedule->id)->assertNotFound();
        $this->get('/business/playlists/'.$playlist->id)->assertNotFound();
        $advertising = $business->playlists()->create(['name' => 'Ads', 'type' => 'advertising', 'status' => 'active']);
        $legacyPayload = $this->payload($media);
        unset($legacyPayload['items']);
        $legacyPayload['playlist_id'] = $advertising->id;
        $this->postJson('/business/schedule', $legacyPayload)->assertUnprocessable()->assertJsonValidationErrors('playlist_id');
        $this->assertSame(1, ContentSchedule::query()->count());
    }

    public function test_search_only_links_schedule_results_to_the_unified_form(): void
    {
        [$business, $user, $media] = $this->scenario();
        $this->actingAs($user)->post('/business/schedule', $this->payload($media))->assertSessionHasNoErrors();
        $schedule = ContentSchedule::query()->firstOrFail();
        $business->playlists()->create(['name' => 'Menú huérfano', 'type' => 'business', 'status' => 'active', 'is_schedule_managed' => true]);
        $business->playlists()->create(['name' => 'Menú anterior', 'type' => 'business', 'status' => 'active']);
        $foreign = Business::factory()->create();
        $foreignPlaylist = $foreign->playlists()->create(['name' => 'Menú ajeno', 'type' => 'business', 'status' => 'active']);
        $foreign->schedules()->create(['name' => 'Menú ajeno', 'playlist_id' => $foreignPlaylist->id, 'status' => 'active']);
        $this->getJson('/business/search?q=Men%C3%BA')->assertOk()
            ->assertJsonCount(1, 'groups')->assertJsonPath('groups.0.label', 'Programación')
            ->assertJsonCount(1, 'groups.0.items')->assertJsonPath('groups.0.items.0.href', '/business/schedule?edit='.$schedule->id);
    }

    public function test_media_picker_can_page_and_search_the_entire_owned_ready_library(): void
    {
        [$business, $user, $oldest] = $this->scenario();
        $oldest->update(['filename' => 'Antigua única.jpg']);
        MediaAsset::factory()->count(100)->image()->create([
            'owner_type' => $business->getMorphClass(), 'owner_id' => $business->id,
        ]);
        MediaAsset::factory()->image()->create(['filename' => 'Antigua ajena.jpg']);
        MediaAsset::factory()->image()->create([
            'owner_type' => $business->getMorphClass(), 'owner_id' => $business->id,
            'filename' => 'Antigua pendiente.jpg', 'processing_status' => 'pending',
        ]);
        $this->actingAs($user)->getJson('/business/schedule/media?page=5')->assertOk()
            ->assertJsonPath('media.total', 101)->assertJsonPath('media.current_page', 5)
            ->assertJsonCount(5, 'media.data')->assertJsonFragment(['id' => $oldest->id]);
        $this->getJson('/business/schedule/media?search=Antigua')->assertOk()
            ->assertJsonCount(1, 'media.data')->assertJsonPath('media.data.0.id', $oldest->id);
        $this->getJson('/business/schedule/media?page=-1')->assertUnprocessable();
        $role = $user->roles()->firstOrFail();
        $role->permissions()->detach(Permission::query()->where('name', 'business.media.view')->value('id'));
        $this->getJson('/business/schedule/media')->assertForbidden();
    }
}
