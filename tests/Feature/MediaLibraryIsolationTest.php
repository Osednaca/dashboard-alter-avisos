<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Media\Jobs\ProcessMediaAsset;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\QuickPlay\Models\QuickPlay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class MediaLibraryIsolationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_admin_library_and_selectors_exclude_business_uploads_and_reject_their_ids(): void
    {
        $business = Business::factory()->create();
        $private = MediaAsset::factory()->image()->create(['owner_type' => $business->getMorphClass(), 'owner_id' => $business->id]);
        $public = MediaAsset::factory()->image()->create();
        $this->actingAs($this->superAdmin());
        $this->get('/admin/creatives')->assertInertia(fn ($page) => $page->has('assets.data', 1)->where('assets.data.0.id', $public->id));
        $this->get('/admin/campaigns/create')->assertInertia(fn ($page) => $page->has('options.creatives', 1)->where('options.creatives.0.id', $public->id));
        $this->get('/admin/quick-play/create')->assertInertia(fn ($page) => $page->has('options.media', 1)->where('options.media.0.id', $public->id));
        $this->postJson('/admin/quick-play', ['media_asset_id' => $private->id, 'duration' => 5, 'scope' => 'all', 'display_mode' => 'fullscreen'])->assertUnprocessable()->assertJsonValidationErrors('media_asset_id');
        $this->delete("/admin/creatives/{$private->id}")->assertNotFound();
        $this->assertDatabaseHas('media_assets', ['id' => $private->id]);
        $businessPlay = QuickPlay::query()->create([
            'user_id' => auth()->id(), 'business_id' => $business->id,
            'media_asset_id' => $private->id, 'duration' => 5, 'scope' => 'all',
            'display_mode' => 'fullscreen', 'status' => 'sending', 'expires_at' => now()->addMinute(),
        ]);
        $this->get('/admin/quick-play')->assertInertia(fn ($page) => $page->has('quickPlays.data', 0));
        $this->get("/admin/quick-play/{$businessPlay->id}")->assertNotFound();
    }

    public function test_multiple_independent_uploads_preserve_valid_files_when_one_fails(): void
    {
        Queue::fake();
        Storage::fake('public');
        config(['signage.media_disk' => 'public']);
        $this->actingAs($this->superAdmin());
        $this->postJson('/admin/creatives', ['file' => UploadedFile::fake()->image('one.jpg')])->assertCreated();
        $this->postJson('/admin/creatives', ['file' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain')])->assertUnprocessable();
        $this->postJson('/admin/creatives', ['file' => UploadedFile::fake()->image('two.png')])->assertCreated();
        $this->assertDatabaseCount('media_assets', 2);
        foreach (MediaAsset::all() as $media) {
            Storage::disk('public')->assertExists($media->storage_path);
        }
        Queue::assertPushed(ProcessMediaAsset::class, 2);
    }

    public function test_business_uploads_keep_ownership_and_stay_out_of_admin_library(): void
    {
        Queue::fake();
        Storage::fake('public');
        config(['signage.media_disk' => 'public']);
        $business = Business::factory()->create();
        $this->actingAs($this->businessUser($business));
        foreach (['one.jpg', 'two.jpg'] as $name) {
            $this->postJson('/business/library', ['file' => UploadedFile::fake()->image($name), 'business_id' => 999])->assertCreated();
        }
        $this->assertSame(2, MediaAsset::where('owner_id', $business->id)->where('owner_type', $business->getMorphClass())->count());
        $this->actingAs($this->superAdmin())->get('/admin/creatives')->assertInertia(fn ($page) => $page->has('assets.data', 0));
    }
}
