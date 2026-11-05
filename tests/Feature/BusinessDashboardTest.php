<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Playlists\Enums\PlaylistType;
use App\Domain\Playlists\Models\Playlist;
use App\Domain\Scheduling\Models\ContentSchedule;
use App\Domain\Users\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BusinessDashboardTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function businessWithUser(): array
    {
        $business = Business::factory()->create();
        $user = $this->businessUser($business);

        return [$business, $user];
    }

    protected function businessPlaylist(Business $business): Playlist
    {
        return $business->playlists()->create([
            'name' => 'Menú Principal',
            'type' => PlaylistType::Business,
            'status' => 'active',
        ]);
    }

    public function test_business_user_can_view_the_dashboard(): void
    {
        [$business, $user] = $this->businessWithUser();

        $this->actingAs($user)
            ->get('/business/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Business/Home'));
    }

    public function test_all_business_pages_render(): void
    {
        [$business, $user] = $this->businessWithUser();

        $routes = [
            '/business/content',
            '/business/library',
            '/business/schedule',
            '/business/screens',
            '/business/preview',
            '/business/reports',
            '/business/settings',
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)->get($route)->assertOk();
        }
    }

    public function test_users_without_a_business_cannot_access_the_business_area(): void
    {
        $support = $this->userWithRole(RoleEnum::Support);

        $this->actingAs($support)->get('/business/dashboard')->assertForbidden();
    }

    public function test_business_users_cannot_access_the_admin_area(): void
    {
        [$business, $user] = $this->businessWithUser();

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/admin/campaigns')->assertForbidden();
    }

    public function test_business_cannot_view_another_business_screen(): void
    {
        [$business, $user] = $this->businessWithUser();
        $other = Business::factory()->create();
        $otherDevice = Device::factory()->create(['business_id' => $other->id]);

        $this->actingAs($user)->get("/business/screens/{$otherDevice->id}")->assertNotFound();
        $this->actingAs($user)->post("/business/screens/{$otherDevice->id}/sync")->assertNotFound();
    }

    public function test_business_cannot_touch_another_business_media(): void
    {
        [$business, $user] = $this->businessWithUser();
        $other = Business::factory()->create();
        $otherMedia = MediaAsset::factory()->create([
            'owner_type' => (new Business)->getMorphClass(),
            'owner_id' => $other->id,
        ]);

        $this->actingAs($user)->put("/business/library/{$otherMedia->id}", ['filename' => 'x.jpg'])->assertNotFound();
        $this->actingAs($user)->delete("/business/library/{$otherMedia->id}")->assertNotFound();
    }

    public function test_business_cannot_open_another_business_playlist(): void
    {
        [$business, $user] = $this->businessWithUser();
        $other = Business::factory()->create();
        $otherPlaylist = $this->businessPlaylist($other);

        $this->actingAs($user)->get("/business/playlists/{$otherPlaylist->id}")->assertNotFound();
        $this->actingAs($user)->delete("/business/playlists/{$otherPlaylist->id}")->assertNotFound();
    }

    public function test_a_business_can_manage_its_playlist(): void
    {
        [$business, $user] = $this->businessWithUser();
        $playlist = $this->businessPlaylist($business);
        $media = MediaAsset::factory()->create([
            'owner_type' => (new Business)->getMorphClass(),
            'owner_id' => $business->id,
        ]);

        $this->actingAs($user)
            ->post("/business/playlists/{$playlist->id}/items", [
                'media_asset_id' => $media->id,
                'duration' => 12,
                'transition' => 'fade',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('playlist_items', [
            'playlist_id' => $playlist->id,
            'media_asset_id' => $media->id,
            'duration' => 12,
        ]);

        $item = $playlist->items()->firstOrFail();

        $this->actingAs($user)
            ->put("/business/playlists/{$playlist->id}/items/reorder", ['order' => [$item->id]])
            ->assertRedirect();
    }

    public function test_a_business_cannot_add_content_to_another_business_playlist(): void
    {
        [$business, $user] = $this->businessWithUser();
        $other = Business::factory()->create();
        $otherPlaylist = $this->businessPlaylist($other);
        $media = MediaAsset::factory()->create([
            'owner_type' => (new Business)->getMorphClass(),
            'owner_id' => $business->id,
        ]);

        $this->actingAs($user)
            ->post("/business/playlists/{$otherPlaylist->id}/items", [
                'media_asset_id' => $media->id,
                'duration' => 10,
                'transition' => 'fade',
            ])
            ->assertNotFound();
    }

    public function test_media_uploads_are_owned_by_the_business(): void
    {
        Storage::fake(config('signage.media_disk'));

        [$business, $user] = $this->businessWithUser();

        $this->actingAs($user)
            ->post('/business/library', ['file' => UploadedFile::fake()->image('promo.jpg', 800, 600)])
            ->assertRedirect();

        $this->assertDatabaseHas('media_assets', [
            'owner_type' => (new Business)->getMorphClass(),
            'owner_id' => $business->id,
        ]);
    }

    public function test_business_can_create_a_schedule_and_gets_overlap_warning(): void
    {
        [$business, $user] = $this->businessWithUser();
        $playlist = $this->businessPlaylist($business);

        $this->actingAs($user)
            ->post('/business/schedule', [
                'name' => 'Desayuno',
                'playlist_id' => $playlist->id,
                'daily_start_time' => '06:00',
                'daily_end_time' => '11:00',
                'days_of_week' => [],
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('content_schedules', ['business_id' => $business->id, 'name' => 'Desayuno']);

        $this->actingAs($user)
            ->post('/business/schedule', [
                'name' => 'Almuerzo',
                'playlist_id' => $playlist->id,
                'daily_start_time' => '10:00',
                'daily_end_time' => '12:00',
                'days_of_week' => [],
                'status' => 'active',
            ])
            ->assertSessionHas('info');

        $this->assertSame(2, ContentSchedule::query()->where('business_id', $business->id)->count());
    }

    public function test_business_can_sync_its_own_screen(): void
    {
        [$business, $user] = $this->businessWithUser();
        $device = Device::factory()->create(['business_id' => $business->id]);

        $this->actingAs($user)
            ->post("/business/screens/{$device->id}/sync")
            ->assertRedirect();

        $this->assertDatabaseCount('device_manifests', 1);
    }

    public function test_business_can_update_its_settings(): void
    {
        [$business, $user] = $this->businessWithUser();

        $this->actingAs($user)
            ->put('/business/settings', [
                'name' => 'La Hamburguesería',
                'timezone' => 'America/Bogota',
                'contact_email' => 'hola@lahamburgueseria.co',
                'audio_volume' => 55,
                'notify_email' => true,
                'notify_offline' => false,
            ])
            ->assertRedirect();

        $business->refresh();
        $this->assertSame('La Hamburguesería', $business->name);
        $this->assertSame(55, (int) data_get($business->metadata, 'audio_volume'));
    }

    public function test_business_cannot_update_global_system_settings(): void
    {
        [$business, $user] = $this->businessWithUser();

        $this->actingAs($user)->get('/admin/settings')->assertForbidden();
    }
}
