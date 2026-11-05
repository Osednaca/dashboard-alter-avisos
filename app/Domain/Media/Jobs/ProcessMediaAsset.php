<?php

namespace App\Domain\Media\Jobs;

use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\ProcessingStatus;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ProcessMediaAsset implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public MediaAsset $asset) {}

    /**
     * Extract metadata and generate a thumbnail. Heavy video transcoding is
     * handled by a dedicated worker with FFmpeg installed.
     */
    public function handle(): void
    {
        $asset = $this->asset;
        $asset->forceFill(['processing_status' => ProcessingStatus::Processing])->save();

        try {
            $disk = config('signage.media_disk');
            $path = Storage::disk($disk)->path($asset->storage_path);

            $asset->forceFill([
                'filesize' => $asset->filesize ?: (@filesize($path) ?: null),
                'checksum' => $asset->checksum ?: (@hash_file('sha256', $path) ?: null),
            ]);

            if ($asset->type === MediaType::Image) {
                $this->processImage($asset, $path);
            } else {
                $this->processVideo($asset, $path);
            }

            if (! $asset->thumbnail_path && $asset->type === MediaType::Image) {
                $asset->thumbnail_path = $asset->storage_path;
            }

            $asset->processing_status = ProcessingStatus::Ready;
            $asset->save();
        } catch (\Throwable $e) {
            $asset->forceFill(['processing_status' => ProcessingStatus::Failed])->save();

            Log::error('Media processing failed', [
                'media_asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function processImage(MediaAsset $asset, string $path): void
    {
        $info = @getimagesize($path);

        if (is_array($info)) {
            $asset->width = $info[0] ?? null;
            $asset->height = $info[1] ?? null;
        }
    }

    protected function processVideo(MediaAsset $asset, string $path): void
    {
        $probe = $this->ffprobe($path);

        if (is_array($probe)) {
            $asset->width = $probe['width'] ?? null;
            $asset->height = $probe['height'] ?? null;
            $asset->duration = isset($probe['duration']) ? (int) round((float) $probe['duration']) : null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function ffprobe(string $path): ?array
    {
        try {
            $process = new Process([
                'ffprobe', '-v', 'error',
                '-select_streams', 'v:0',
                '-show_entries', 'stream=width,height,duration',
                '-of', 'json',
                $path,
            ]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $data = json_decode($process->getOutput(), true);
            $stream = $data['streams'][0] ?? [];

            return [
                'width' => $stream['width'] ?? null,
                'height' => $stream['height'] ?? null,
                'duration' => $stream['duration'] ?? null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
