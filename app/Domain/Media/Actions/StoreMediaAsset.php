<?php

namespace App\Domain\Media\Actions;

use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\ProcessingStatus;
use App\Domain\Media\Jobs\ProcessMediaAsset;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreMediaAsset
{
    /**
     * Persist an uploaded creative to object storage and queue processing.
     */
    public function handle(UploadedFile $file, MediaType $type, ?Model $owner = null): MediaAsset
    {
        $disk = config('signage.media_disk');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $directory = 'media/'.$type->value.'/'.now()->format('Y/m');

        $path = $file->storeAs($directory, $filename, $disk);
        if (! $path) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }

        $checksum = null;
        try {
            $checksum = hash_file('sha256', Storage::disk($disk)->path($path));
        } catch (\Throwable) {
            // Checksums are best-effort for local disks that are not readable.
        }

        $asset = MediaAsset::query()->create([
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
            'type' => $type,
            'filename' => $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'filesize' => $file->getSize(),
            'checksum' => $checksum,
            'processing_status' => ProcessingStatus::Pending,
        ]);

        ProcessMediaAsset::dispatch($asset);

        return $asset;
    }
}
