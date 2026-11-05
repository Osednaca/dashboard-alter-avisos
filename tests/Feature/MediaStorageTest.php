<?php

namespace Tests\Feature;

use App\Domain\Media\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_files_and_preview_urls_survive_recreating_the_storage_adapter(): void
    {
        $disk = Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);
        config(['signage.media_disk' => 'public']);
        $image = MediaAsset::factory()->image()->create(['storage_path' => 'media/menu.jpg', 'thumbnail_path' => 'media/menu.jpg']);
        $video = MediaAsset::factory()->video()->create(['storage_path' => 'media/video.mp4', 'thumbnail_path' => 'media/video.jpg']);
        $disk->put($image->storage_path, 'image-content');
        $disk->put($video->storage_path, 'video-content');
        $disk->put($video->thumbnail_path, 'poster-content');
        $urls = [$image->url, $image->thumbnail_url, $video->url, $video->thumbnail_url];
        // A new process/release opens the same persistent directory.
        $root = $disk->path('');
        config(['filesystems.disks.public.root' => $root]);
        Storage::forgetDisk('public');
        $this->assertSame($urls, [$image->fresh()->url, $image->fresh()->thumbnail_url, $video->fresh()->url, $video->fresh()->thumbnail_url]);
        $this->assertSame('image-content', Storage::disk('public')->get($image->storage_path));
        $this->assertSame('video-content', Storage::disk('public')->get($video->storage_path));
        $this->assertSame('poster-content', Storage::disk('public')->get($video->thumbnail_path));
        $this->artisan('signage:media-check')->expectsOutput('Archivos registrados: 2. Rutas ausentes: 0.')->assertSuccessful();
    }

    public function test_audit_reports_lost_originals_and_previews_without_deleting_records(): void
    {
        $disk = Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);
        config(['signage.media_disk' => 'public']);
        $image = MediaAsset::factory()->image()->create(['storage_path' => 'media/lost.jpg', 'thumbnail_path' => 'media/lost.jpg']);
        $video = MediaAsset::factory()->video()->create(['storage_path' => 'media/video.mp4', 'thumbnail_path' => 'media/missing-poster.jpg']);
        $disk->put($video->storage_path, 'keep-this-video');
        $this->artisan('signage:media-check')->expectsOutput('Archivos registrados: 2. Rutas ausentes: 2.')->assertFailed();
        $this->assertDatabaseCount('media_assets', 2);
        $this->assertSame($image->storage_path, $image->fresh()->storage_path);
        $this->assertSame('keep-this-video', $disk->get($video->storage_path));
    }
}
