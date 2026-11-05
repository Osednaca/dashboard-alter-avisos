<?php

namespace Database\Factories;

use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\ProcessingStatus;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isVideo = fake()->boolean(40);
        $type = $isVideo ? MediaType::Video : MediaType::Image;
        $filename = Str::slug(fake()->words(3, true)).($isVideo ? '.mp4' : '.jpg');

        return [
            'type' => $type->value,
            'filename' => $filename,
            'original_name' => $filename,
            'storage_path' => 'media/'.$type->value.'/'.now()->format('Y/m').'/'.$filename,
            'thumbnail_path' => null,
            'mime_type' => $isVideo ? 'video/mp4' : 'image/jpeg',
            'width' => 1920,
            'height' => fake()->randomElement([1080, 1080, 1080, 720]),
            'duration' => $isVideo ? fake()->numberBetween(10, 45) : null,
            'filesize' => fake()->numberBetween(200_000, 40_000_000),
            'checksum' => hash('sha256', $filename.Str::random(6)),
            'processing_status' => ProcessingStatus::Ready->value,
            'metadata' => ['source' => 'seed'],
        ];
    }

    public function image(): static
    {
        return $this->state(fn () => ['type' => MediaType::Image->value, 'mime_type' => 'image/jpeg', 'duration' => null]);
    }

    public function video(): static
    {
        return $this->state(fn () => ['type' => MediaType::Video->value, 'mime_type' => 'video/mp4', 'duration' => fake()->numberBetween(10, 45)]);
    }
}
