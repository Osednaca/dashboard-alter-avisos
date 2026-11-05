<?php

namespace App\Http\Presenters;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignTarget;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceActivation;
use App\Domain\Devices\Models\DeviceCommand;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Services\LiveSourcePayload;
use App\Domain\Operations\Models\Alert;
use App\Domain\Operations\Models\AuditLog;
use App\Domain\Playlists\Models\Playlist;
use App\Domain\Playlists\Models\PlaylistItem;
use App\Domain\QuickPlay\Models\QuickPlay;
use App\Domain\QuickPlay\Models\QuickPlayDevice;
use App\Domain\Scheduling\Models\ContentSchedule;
use App\Domain\Users\Models\Role;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

class EntityPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function enum(?BackedEnum $enum): ?array
    {
        if ($enum === null) {
            return null;
        }

        $result = ['value' => $enum->value];

        if (method_exists($enum, 'label')) {
            $result['label'] = $enum->label();
        }

        if (method_exists($enum, 'tone')) {
            $result['tone'] = $enum->tone();
        }

        return $result;
    }

    public static function user(User $user): array
    {
        $primaryBusiness = $user->relationLoaded('businesses')
            ? ($user->businesses->first(fn ($business) => (bool) $business->pivot->is_primary) ?? $user->businesses->first())
            : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'job_title' => $user->job_title,
            'avatar_url' => $user->avatar_url,
            'initials' => $user->initials(),
            'status' => self::enum($user->status),
            'roles' => $user->roleNames(),
            'business_id' => $primaryBusiness?->id,
            'business_name' => $primaryBusiness?->name,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    public static function role(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $role->label,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'permissions' => $role->relationLoaded('permissions')
                ? $role->permissions->pluck('name')->all()
                : [],
            'users_count' => $role->users_count ?? null,
        ];
    }

    public static function business(Business $business): array
    {
        $layout = null;
        if ($business->relationLoaded('devices')) {
            $withLayout = $business->devices->first(fn ($device) => $device->currentLayout !== null);
            if ($withLayout?->currentLayout) {
                $layout = [
                    'id' => $withLayout->currentLayout->id,
                    'name' => $withLayout->currentLayout->name,
                    'ratio' => $withLayout->currentLayout->ratioLabel(),
                ];
            }
        }

        return [
            'id' => $business->id,
            'name' => $business->name,
            'slug' => $business->slug,
            'category' => self::enum($business->category),
            'status' => self::enum($business->status),
            'timezone' => $business->timezone,
            'contact_name' => $business->contact_name,
            'contact_email' => $business->contact_email,
            'contact_phone' => $business->contact_phone,
            'metadata' => $business->metadata,
            'locations_count' => (int) ($business->locations_count ?? 0),
            'devices_count' => (int) ($business->devices_count ?? 0),
            'online_devices_count' => (int) ($business->online_devices_count ?? 0),
            'current_layout' => $layout,
            'created_at' => $business->created_at?->toIso8601String(),
        ];
    }

    public static function location(Location $location): array
    {
        return [
            'id' => $location->id,
            'business' => $location->relationLoaded('business') && $location->business
                ? ['id' => $location->business->id, 'name' => $location->business->name]
                : null,
            'name' => $location->name,
            'city' => $location->city,
            'state' => $location->state,
            'country' => $location->country,
            'address' => $location->address,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'timezone' => $location->timezone,
            'status' => self::enum($location->status),
            'devices_count' => (int) ($location->devices_count ?? 0),
            'online_devices_count' => (int) ($location->online_devices_count ?? 0),
            'created_at' => $location->created_at?->toIso8601String(),
        ];
    }

    public static function device(Device $device): array
    {
        return [
            'id' => $device->id,
            'uuid' => $device->uuid,
            'name' => $device->name,
            'status' => self::enum($device->status),
            'is_online' => $device->isOnline(),
            'business' => $device->relationLoaded('business') && $device->business
                ? ['id' => $device->business->id, 'name' => $device->business->name]
                : null,
            'location' => $device->relationLoaded('location') && $device->location
                ? ['id' => $device->location->id, 'name' => $device->location->name, 'city' => $device->location->city]
                : null,
            'app_version' => $device->app_version,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_sync_at' => $device->last_sync_at?->toIso8601String(),
            'storage_total' => $device->storage_total,
            'storage_free' => $device->storage_free,
            'storage_usage' => $device->storageUsagePercentage(),
            'current_manifest_version' => $device->current_manifest_version,
            'pending_manifest_version' => $device->pending_manifest_version,
            'current_layout' => $device->relationLoaded('currentLayout') && $device->currentLayout
                ? ['id' => $device->currentLayout->id, 'name' => $device->currentLayout->name, 'ratio' => $device->currentLayout->ratioLabel()]
                : null,
            'current_playlist' => $device->relationLoaded('currentPlaylist') && $device->currentPlaylist
                && ($device->currentPlaylist->business_id === null || (int) $device->currentPlaylist->business_id === (int) $device->business_id)
                ? ['id' => $device->currentPlaylist->id, 'name' => $device->currentPlaylist->name]
                : null,
            'created_at' => $device->created_at?->toIso8601String(),
        ];
    }

    public static function deviceCommand(DeviceCommand $command): array
    {
        return [
            'id' => $command->id,
            'command' => self::enum($command->command),
            'status' => self::enum($command->status),
            'payload' => $command->payload,
            'result' => $command->result,
            'error' => $command->error,
            'created_by' => $command->relationLoaded('creator') && $command->creator ? $command->creator->name : null,
            'sent_at' => $command->sent_at?->toIso8601String(),
            'executed_at' => $command->executed_at?->toIso8601String(),
            'created_at' => $command->created_at?->toIso8601String(),
        ];
    }

    public static function activation(DeviceActivation $activation): array
    {
        return [
            'id' => $activation->id,
            'code' => $activation->code,
            'status' => self::enum($activation->status),
            'device_uuid' => $activation->device_uuid,
            'device_name' => $activation->device_name,
            'business' => $activation->relationLoaded('business') && $activation->business
                ? ['id' => $activation->business->id, 'name' => $activation->business->name]
                : null,
            'location' => $activation->relationLoaded('location') && $activation->location
                ? ['id' => $activation->location->id, 'name' => $activation->location->name]
                : null,
            'app_version' => $activation->app_version,
            'ip_address' => $activation->ip_address,
            'expires_at' => $activation->expires_at?->toIso8601String(),
            'claimed_at' => $activation->claimed_at?->toIso8601String(),
            'created_at' => $activation->created_at?->toIso8601String(),
        ];
    }

    public static function advertiser(Advertiser $advertiser): array
    {
        return [
            'id' => $advertiser->id,
            'name' => $advertiser->name,
            'slug' => $advertiser->slug,
            'status' => self::enum($advertiser->status),
            'contact_name' => $advertiser->contact_name,
            'contact_email' => $advertiser->contact_email,
            'contact_phone' => $advertiser->contact_phone,
            'billing_name' => $advertiser->billing_name,
            'billing_tax_id' => $advertiser->billing_tax_id,
            'billing_email' => $advertiser->billing_email,
            'billing_address' => $advertiser->billing_address,
            'metadata' => $advertiser->metadata,
            'active_campaigns_count' => (int) ($advertiser->active_campaigns_count ?? 0),
            'campaigns_count' => (int) ($advertiser->campaigns_count ?? 0),
            'total_screens' => (int) ($advertiser->total_screens ?? 0),
            'total_playbacks' => (int) ($advertiser->total_playbacks ?? 0),
            'created_at' => $advertiser->created_at?->toIso8601String(),
        ];
    }

    public static function campaign(Campaign $campaign): array
    {
        $playbacks = (int) ($campaign->playbacks_count ?? 0);
        $completed = (int) ($campaign->completed_count ?? 0);

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'status' => self::enum($campaign->status),
            'advertiser' => $campaign->relationLoaded('advertiser') && $campaign->advertiser
                ? ['id' => $campaign->advertiser->id, 'name' => $campaign->advertiser->name]
                : null,
            'starts_at' => $campaign->starts_at?->toDateString(),
            'ends_at' => $campaign->ends_at?->toDateString(),
            'daily_start_time' => $campaign->daily_start_time,
            'daily_end_time' => $campaign->daily_end_time,
            'days_of_week' => $campaign->days_of_week,
            'schedule_label' => $campaign->scheduleLabel(),
            'priority' => $campaign->priority,
            'playback_goal' => $campaign->playback_goal,
            'impressions_goal' => $campaign->impressions_goal,
            'budget' => $campaign->budget,
            'target_screen_count' => $campaign->target_screen_count,
            'creatives_count' => (int) ($campaign->creatives_count ?? 0),
            'playbacks_count' => $playbacks,
            'completion_rate' => $playbacks > 0 ? round(($completed / $playbacks) * 100, 1) : 0.0,
            'progress' => $campaign->playback_goal
                ? min(100, (int) round(($playbacks / $campaign->playback_goal) * 100))
                : null,
            'published_at' => $campaign->published_at?->toIso8601String(),
            'updated_at' => $campaign->updated_at?->toIso8601String(),
        ];
    }

    public static function campaignTarget(CampaignTarget $target): array
    {
        return [
            'id' => $target->id,
            'target_type' => self::enum($target->target_type),
            'target_id' => $target->target_id,
            'target_value' => $target->target_value,
            'is_exclusion' => $target->is_exclusion,
        ];
    }

    public static function mediaAsset(MediaAsset $asset): array
    {
        return [
            'live' => app(LiveSourcePayload::class)->forAsset($asset),
            'id' => $asset->id,
            'filename' => $asset->filename,
            'type' => self::enum($asset->type),
            'mime_type' => $asset->mime_type,
            'url' => $asset->url,
            'thumbnail_url' => $asset->thumbnail_url,
            'resolution' => $asset->resolution,
            'width' => $asset->width,
            'height' => $asset->height,
            'duration' => $asset->duration,
            'formatted_duration' => $asset->formatted_duration,
            'filesize' => $asset->filesize,
            'human_filesize' => $asset->human_filesize,
            'checksum' => $asset->checksum,
            'processing_status' => self::enum($asset->processing_status),
            'usage_count' => (int) ($asset->usage_count ?? 0),
            'created_at' => $asset->created_at?->toIso8601String(),
        ];
    }

    public static function playlist(Playlist $playlist): array
    {
        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'type' => self::enum($playlist->type),
            'status' => self::enum($playlist->status),
            'business' => $playlist->relationLoaded('business') && $playlist->business
                ? ['id' => $playlist->business->id, 'name' => $playlist->business->name]
                : null,
            'items_count' => (int) ($playlist->items_count ?? 0),
            'updated_at' => $playlist->updated_at?->toIso8601String(),
        ];
    }

    public static function alert(Alert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => self::enum($alert->type),
            'severity' => self::enum($alert->severity),
            'status' => self::enum($alert->status),
            'title' => $alert->title,
            'message' => $alert->message,
            'subject_type' => $alert->alertable_type ? class_basename($alert->alertable_type) : null,
            'subject_id' => $alert->alertable_id,
            'metadata' => $alert->metadata,
            'triggered_at' => $alert->triggered_at?->toIso8601String(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
        ];
    }

    public static function auditLog(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'user' => $log->relationLoaded('user') && $log->user ? $log->user->name : 'Sistema',
            'action' => $log->action,
            'entity_type' => $log->entity_type ? class_basename($log->entity_type) : null,
            'entity_id' => $log->entity_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    public static function quickPlay(QuickPlay $quickPlay): array
    {
        $duration = $quickPlay->duration;

        return [
            'id' => $quickPlay->id,
            'media' => $quickPlay->relationLoaded('mediaAsset') && $quickPlay->mediaAsset
                ? self::mediaAsset($quickPlay->mediaAsset)
                : null,
            'user' => $quickPlay->relationLoaded('user') && $quickPlay->user ? $quickPlay->user->name : 'Sistema',
            'display_mode' => self::enum($quickPlay->display_mode),
            'scope' => self::enum($quickPlay->scope),
            'duration' => $duration,
            'duration_label' => $duration ? "{$duration} s" : 'Duración natural del video',
            'can_retry' => $quickPlay->canRetry(),
            'retry_id' => $quickPlay->metadata['retry_id'] ?? null,
            'retry_of_id' => $quickPlay->metadata['retry_of_id'] ?? null,
            'status' => self::enum($quickPlay->status),
            'targets_count' => $quickPlay->targets_count,
            'delivered_count' => $quickPlay->delivered_count,
            'failed_count' => $quickPlay->failed_count,
            'pending_count' => max(0, $quickPlay->targets_count - $quickPlay->delivered_count - $quickPlay->failed_count),
            'expires_at' => $quickPlay->expires_at?->toIso8601String(),
            'created_at' => $quickPlay->created_at?->toIso8601String(),
        ];
    }

    public static function quickPlayDevice(QuickPlayDevice $row): array
    {
        return [
            'id' => $row->id,
            'device' => $row->relationLoaded('device') && $row->device
                ? [
                    'id' => $row->device->id,
                    'name' => $row->device->name,
                    'business' => $row->device->relationLoaded('business') && $row->device->business
                        ? $row->device->business->name
                        : null,
                    'city' => $row->device->relationLoaded('location') && $row->device->location
                        ? $row->device->location->city
                        : null,
                ]
                : null,
            'status' => self::enum($row->status),
            'error' => $row->error,
            'display_mode' => self::enum($row->display_mode),
            'duration' => $row->duration,
            'sent_at' => $row->sent_at?->toIso8601String(),
            'started_at' => $row->started_at?->toIso8601String(),
            'completed_at' => $row->completed_at?->toIso8601String(),
        ];
    }

    public static function businessSelf(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->name,
            'slug' => $business->slug,
            'logo_url' => $business->logo_url,
            'category' => self::enum($business->category),
            'status' => self::enum($business->status),
            'timezone' => $business->timezone,
            'contact_name' => $business->contact_name,
            'contact_email' => $business->contact_email,
            'contact_phone' => $business->contact_phone,
            'metadata' => $business->metadata,
        ];
    }

    public static function playlistSummary(Playlist $playlist): array
    {
        $items = $playlist->relationLoaded('items') ? $playlist->items : collect();
        $firstWithMedia = $items->first(fn (PlaylistItem $item) => $item->mediaAsset !== null);

        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'type' => self::enum($playlist->type),
            'status' => self::enum($playlist->status),
            'items_count' => $playlist->relationLoaded('items') ? $items->count() : (int) ($playlist->items_count ?? 0),
            'total_duration' => (int) $items->sum('duration'),
            'cover' => $firstWithMedia?->mediaAsset ? self::mediaAsset($firstWithMedia->mediaAsset) : null,
            'updated_at' => $playlist->updated_at?->toIso8601String(),
        ];
    }

    public static function playlistItem(PlaylistItem $item): array
    {
        return [
            'id' => $item->id,
            'playlist_id' => $item->playlist_id,
            'sort_order' => $item->sort_order,
            'duration' => $item->duration,
            'transition' => $item->transition,
            'media' => $item->relationLoaded('mediaAsset') && $item->mediaAsset
                ? self::mediaAsset($item->mediaAsset)
                : null,
        ];
    }

    public static function contentSchedule(ContentSchedule $schedule): array
    {
        $days = $schedule->days_of_week ?? [];
        $timezone = $schedule->relationLoaded('business') && $schedule->business
            ? $schedule->business->timezone
            : config('app.timezone');
        $now = now($timezone);
        $today = (int) $now->dayOfWeekIso;
        $time = $now->format('H:i:s');

        $matchesDay = $days === [] || in_array($today, $days, true);
        $matchesTime = (! $schedule->daily_start_time || $time >= $schedule->daily_start_time)
            && (! $schedule->daily_end_time || $time <= $schedule->daily_end_time);

        return [
            'id' => $schedule->id,
            'name' => $schedule->name ?: ($schedule->relationLoaded('playlist') && $schedule->playlist ? $schedule->playlist->name : 'Programación'),
            'daily_start_time' => $schedule->daily_start_time,
            'priority' => $schedule->priority,
            'items' => $schedule->relationLoaded('playlist') && $schedule->playlist?->relationLoaded('items')
                ? $schedule->playlist->items->map(fn (PlaylistItem $item) => self::playlistItem($item))->values()->all()
                : [],
            'daily_end_time' => $schedule->daily_end_time,
            'days_of_week' => $days,
            'status' => $schedule->status,
            'is_active_now' => $schedule->status === 'active' && $matchesDay && $matchesTime,
            'playlist' => $schedule->relationLoaded('playlist') && $schedule->playlist
                ? ['id' => $schedule->playlist->id, 'name' => $schedule->playlist->name]
                : null,
            'location' => $schedule->relationLoaded('location') && $schedule->location
                ? ['id' => $schedule->location->id, 'name' => $schedule->location->name, 'city' => $schedule->location->city]
                : null,
        ];
    }

    /**
     * @param  iterable<Model>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function collection(iterable $items, string $method): array
    {
        $result = [];

        foreach ($items as $item) {
            $result[] = self::$method($item);
        }

        return $result;
    }
}
