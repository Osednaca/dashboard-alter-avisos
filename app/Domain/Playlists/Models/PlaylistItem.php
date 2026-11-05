<?php

namespace App\Domain\Playlists\Models;

use App\Domain\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['playlist_id', 'media_asset_id', 'sort_order', 'duration', 'transition', 'configuration'])]
class PlaylistItem extends Model
{
    /**
     * Transitions supported by the Android TV player.
     *
     * @var array<string, string>
     */
    public const TRANSITIONS = [
        'none' => 'Sin transición',
        'fade' => 'Fade',
        'crossfade' => 'Crossfade',
        'slide_left' => 'Deslizar a la izquierda',
        'slide_right' => 'Deslizar a la derecha',
        'soft_zoom' => 'Zoom suave',
    ];

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function transitionOptions(): array
    {
        return collect(self::TRANSITIONS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'sort_order' => 'integer',
            'duration' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
