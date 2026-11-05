<?php

namespace Database\Seeders;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Analytics\Actions\AggregateDailyAnalytics;
use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Actions\ResolveCampaignTargets;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Enums\ProcessingStatus;
use App\Domain\Media\Models\Layout;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Operations\Enums\AlertSeverity;
use App\Domain\Operations\Enums\AlertType;
use App\Domain\Operations\Models\Alert;
use App\Domain\Operations\Models\AuditLog;
use App\Domain\Playlists\Models\Playlist;
use App\Domain\QuickPlay\Enums\QuickPlayDeviceStatus;
use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use App\Domain\QuickPlay\Enums\QuickPlayStatus;
use App\Domain\QuickPlay\Models\QuickPlay;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Los datos demo solo se permiten en local/testing. Usa php artisan db:seed --force y php artisan signage:admin.');
        }

        $this->call(SystemDefaultsSeeder::class);
        $layouts = Layout::query()->get();

        $advertisers = $this->seedAdvertisers();
        $media = $this->seedMedia($advertisers);

        $businesses = Business::factory()->count(30)->create();
        $devices = $this->seedLocationsAndDevices($businesses, $layouts);

        $this->seedPlaylists($businesses, $media);
        $campaigns = $this->seedCampaigns($advertisers, $media, $devices);

        $this->seedPlaybackEvents($campaigns, $devices, $media);
        $this->aggregate();
        $this->seedQuickPlays($devices, $media);
        $this->seedAlerts($devices);
        $this->seedAudit();
    }

    /**
     * @param  Collection<int, Device>  $devices
     * @param  Collection<int, MediaAsset>  $media
     */
    protected function seedQuickPlays($devices, $media): void
    {
        if ($devices->isEmpty() || $media->isEmpty()) {
            return;
        }

        $admin = User::query()->where('email', 'admin@signagetv.co')->first() ?? User::query()->first();

        $samples = [
            ['mode' => QuickPlayDisplayMode::Advertising, 'scope' => QuickPlayScope::All, 'duration' => 12, 'status' => QuickPlayStatus::Completed, 'age' => 2],
            ['mode' => QuickPlayDisplayMode::Fullscreen, 'scope' => QuickPlayScope::Businesses, 'duration' => 8, 'status' => QuickPlayStatus::Partial, 'age' => 26],
            ['mode' => QuickPlayDisplayMode::Business, 'scope' => QuickPlayScope::Locations, 'duration' => 15, 'status' => QuickPlayStatus::Completed, 'age' => 50],
        ];

        foreach ($samples as $sample) {
            $createdAt = now()->subHours($sample['age']);

            $quickPlay = QuickPlay::query()->create([
                'user_id' => $admin?->id,
                'media_asset_id' => $media->random()->id,
                'display_mode' => $sample['mode'],
                'scope' => $sample['scope'],
                'duration' => $sample['duration'],
                'status' => QuickPlayStatus::Sending,
                'expires_at' => $createdAt->copy()->addHours(1),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $targets = $devices->filter(fn (Device $d) => ! in_array($d->status, [DeviceStatus::PendingActivation, DeviceStatus::Disabled], true))
                ->shuffle()
                ->take(fake()->numberBetween(3, 6));

            foreach ($targets as $index => $device) {
                $failed = $sample['status'] !== QuickPlayStatus::Completed && $index === 0;

                $quickPlay->devices()->create([
                    'device_id' => $device->id,
                    'status' => $failed ? QuickPlayDeviceStatus::Failed : QuickPlayDeviceStatus::Completed,
                    'display_mode' => $sample['mode'],
                    'duration' => $sample['duration'],
                    'previous_layout_id' => $device->current_layout_id,
                    'previous_playlist_id' => $device->current_playlist_id,
                    'error' => $failed ? 'La pantalla está desconectada; el contenido no pudo entregarse.' : null,
                    'sent_at' => $createdAt,
                    'started_at' => $failed ? null : $createdAt->copy()->addSeconds(5),
                    'completed_at' => $failed ? $createdAt->copy()->addMinutes(1) : $createdAt->copy()->addSeconds($sample['duration'] + 5),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            $quickPlay->refreshProgress();
        }
    }

    /**
     * @return Collection<int, Advertiser>
     */
    protected function seedAdvertisers()
    {
        $names = [
            'Coca-Cola', 'Postobón', 'Alpina', 'Bancolombia', 'Claro Colombia',
            'Tiendas D1', 'Rappi', 'Ramo', 'Servientrega', 'Puntos Colpatria',
        ];

        return collect($names)->map(fn (string $name, int $index) => Advertiser::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => 'active',
            'contact_name' => 'Contacto '.$name,
            'contact_email' => 'marketing@'.Str::slug($name).'.co',
            'contact_phone' => '+57 300 000 00'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'billing_name' => $name.' S.A.S.',
            'billing_tax_id' => '900.'.fake()->numerify('###').'.'.fake()->numerify('###').'-'.fake()->numberBetween(1, 9),
            'billing_email' => 'pagos@'.Str::slug($name).'.co',
            'billing_address' => 'Calle '.fake()->numberBetween(10, 120).' # '.fake()->numberBetween(5, 60).'-'.fake()->numberBetween(1, 90),
        ]));
    }

    /**
     * @param  Collection<int, Advertiser>  $advertisers
     * @return Collection<int, MediaAsset>
     */
    protected function seedMedia($advertisers)
    {
        $subjects = [
            'Combo del día', 'Promoción 2x1', 'Nuevo producto', 'Descuento de temporada',
            'Menú desayuno', 'Feliz cumpleaños', 'Envío gratis', 'Semana de la salud',
            'Torneo de verano', 'Aprovecha hoy', 'Línea premium', 'Café de origen',
            'Moda primavera', 'Rutina express', 'Consulta médica', 'Ofertas relámpago',
            'Apertura de sede', 'Concierto en vivo', 'Pizza familiar', 'Bienestar total',
        ];

        return collect($subjects)->map(function (string $subject, int $index) use ($advertisers) {
            $isVideo = $index % 3 === 0;
            $owner = $advertisers[$index % $advertisers->count()];
            $filename = Str::slug($subject).($isVideo ? '.mp4' : '.jpg');

            return MediaAsset::query()->create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->id,
                'type' => $isVideo ? 'video' : 'image',
                'filename' => $filename,
                'original_name' => $filename,
                'storage_path' => 'media/'.($isVideo ? 'video' : 'image').'/'.now()->format('Y/m').'/'.$filename,
                'thumbnail_path' => $isVideo ? null : 'media/image/'.now()->format('Y/m').'/'.$filename,
                'mime_type' => $isVideo ? 'video/mp4' : 'image/jpeg',
                'width' => 1920,
                'height' => $isVideo ? 1080 : 1080,
                'duration' => $isVideo ? fake()->numberBetween(10, 40) : null,
                'filesize' => fake()->numberBetween(300_000, 35_000_000),
                'checksum' => hash('sha256', $filename),
                'processing_status' => ProcessingStatus::Ready->value,
                'metadata' => ['subject' => $subject],
            ]);
        });
    }

    /**
     * @param  Collection<int, Business>  $businesses
     * @param  Collection<int, Layout>  $layouts
     * @return Collection<int, Device>
     */
    protected function seedLocationsAndDevices($businesses, $layouts)
    {
        $devices = collect();
        $defaultLayout = $layouts->firstWhere('is_default', true) ?? $layouts->first();

        foreach ($businesses as $business) {
            $locationCount = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $locationCount && Location::query()->count() < 60; $i++) {
                $location = Location::factory()->create(['business_id' => $business->id]);

                $deviceCount = fake()->numberBetween(1, 3);

                for ($d = 0; $d < $deviceCount && $devices->count() < 120; $d++) {
                    $device = Device::factory()->create([
                        'business_id' => $business->id,
                        'location_id' => $location->id,
                        'current_layout_id' => $layouts->random()->id,
                    ]);

                    if ($device->status !== DeviceStatus::PendingActivation) {
                        $device->forceFill(['current_manifest_version' => now()->format('YmdHis')])->save();
                    }

                    $devices->push($device);
                }
            }
        }

        return $devices;
    }

    /**
     * @param  Collection<int, Business>  $businesses
     * @param  Collection<int, MediaAsset>  $media
     */
    protected function seedPlaylists($businesses, $media): void
    {
        foreach ($businesses as $business) {
            $playlist = Playlist::query()->create([
                'business_id' => $business->id,
                'name' => 'Contenido de '.$business->name,
                'type' => 'business',
                'status' => 'active',
            ]);

            foreach ($media->shuffle()->take(4) as $index => $asset) {
                $playlist->items()->create([
                    'media_asset_id' => $asset->id,
                    'sort_order' => $index,
                    'duration' => $asset->duration ?? 10,
                    'transition' => 'fade',
                ]);
            }

            Device::query()
                ->where('business_id', $business->id)
                ->update(['current_playlist_id' => $playlist->id]);
        }
    }

    /**
     * @param  Collection<int, Advertiser>  $advertisers
     * @param  Collection<int, MediaAsset>  $media
     * @param  Collection<int, Device>  $devices
     * @return Collection<int, Campaign>
     */
    protected function seedCampaigns($advertisers, $media, $devices)
    {
        $resolver = app(ResolveCampaignTargets::class);

        $briefNames = [
            'Refresca tu momento', 'Lo nuestro nos conecta', 'Belleza que te acompaña',
            'Combo del día', 'Bienestar para todos', 'Conecta con tu ciudad',
            'Energía para tu rutina', 'Ahorra más cada día', 'Sabor de casa',
            'Cuida tu salud hoy',
        ];

        return collect($briefNames)->map(function (string $name, int $index) use ($advertisers, $media, $devices, $resolver) {
            $advertiser = $advertisers[$index % $advertisers->count()];
            $startsAt = now()->subDays(fake()->numberBetween(5, 45));
            $endsAt = now()->addDays(fake()->numberBetween(10, 70));

            $campaign = Campaign::query()->create([
                'advertiser_id' => $advertiser->id,
                'name' => $name,
                'description' => 'Campaña de '.$advertiser->name.' para la red de pantallas en Colombia.',
                'status' => $index < 7 ? CampaignStatus::Active : CampaignStatus::Scheduled,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'daily_start_time' => '07:00:00',
                'daily_end_time' => '22:00:00',
                'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
                'priority' => fake()->numberBetween(3, 10),
                'playback_goal' => fake()->numberBetween(80_000, 1_500_000),
                'impressions_goal' => fake()->numberBetween(100_000, 2_000_000),
                'budget' => fake()->numberBetween(3_000_000, 35_000_000),
                'published_at' => $startsAt,
                'last_activity_at' => now()->subMinutes(fake()->numberBetween(5, 500)),
            ]);

            foreach ($media->shuffle()->take(fake()->numberBetween(1, 3)) as $position => $asset) {
                $campaign->creatives()->create([
                    'media_asset_id' => $asset->id,
                    'duration' => $asset->duration ?? 10,
                    'weight' => fake()->numberBetween(5, 20),
                    'position' => $position,
                    'status' => 'active',
                ]);
            }

            // Extensible targeting: mix of city, category, business and device rules.
            $targets = [];
            $cities = $devices->map(fn (Device $device) => $device->location?->city)->filter()->unique()->values();
            $targets[] = ['target_type' => 'city', 'target_value' => $cities->random(), 'is_exclusion' => false];

            if ($index % 3 === 0) {
                $targets[] = ['target_type' => 'business_category', 'target_value' => fake()->randomElement(['restaurant', 'cafe', 'gym', 'retail']), 'is_exclusion' => false];
            }

            if ($index % 4 === 0) {
                $device = $devices->random();
                $targets[] = ['target_type' => 'business', 'target_id' => $device->business_id, 'is_exclusion' => false];
            }

            foreach ($targets as $target) {
                $campaign->targets()->create($target);
            }

            $campaign->load('targets');
            $campaign->forceFill(['target_screen_count' => $resolver->summary($campaign)['screens']])->save();

            return $campaign;
        });
    }

    /**
     * @param  Collection<int, Campaign>  $campaigns
     * @param  Collection<int, Device>  $devices
     * @param  Collection<int, MediaAsset>  $media
     */
    protected function seedPlaybackEvents($campaigns, $devices, $media): void
    {
        if ($devices->isEmpty() || $media->isEmpty()) {
            return;
        }

        $activeCampaigns = $campaigns->filter(fn (Campaign $c) => $c->status === CampaignStatus::Active)->values();
        $activeDevices = $devices->filter(
            fn (Device $d) => ! in_array($d->status, [DeviceStatus::PendingActivation, DeviceStatus::Disabled], true)
        )->values();

        if ($activeCampaigns->isEmpty() || $activeDevices->isEmpty()) {
            return;
        }

        $now = now();
        $playlistId = Playlist::query()->value('id');

        // Insert day by day so the seeder stays within a small memory budget.
        for ($day = 29; $day >= 0; $day--) {
            $date = $now->copy()->subDays($day);
            $eventCount = fake()->numberBetween(500, 900);
            $rows = [];

            for ($i = 0; $i < $eventCount; $i++) {
                $campaign = $activeCampaigns->random();
                $creative = $campaign->creatives->random();
                $device = $activeDevices->random();
                $startedAt = $date->copy()->setTime(
                    fake()->numberBetween(7, 21),
                    fake()->numberBetween(0, 59),
                    fake()->numberBetween(0, 59),
                );

                $failed = fake()->boolean(4);
                $duration = $creative->duration ?: 10;

                $rows[] = [
                    'device_id' => $device->id,
                    'campaign_id' => $campaign->id,
                    'creative_id' => $creative->id,
                    'playlist_id' => $playlistId,
                    'media_asset_id' => $creative->media_asset_id,
                    'started_at' => $startedAt,
                    'completed_at' => $failed ? null : $startedAt->copy()->addSeconds($duration),
                    'duration_played' => $failed ? fake()->numberBetween(1, max(1, $duration - 1)) : $duration,
                    'completed' => ! $failed,
                    'error_code' => $failed ? fake()->randomElement(['DECODE_ERROR', 'NETWORK_TIMEOUT', 'ASSET_MISSING']) : null,
                    'manifest_version' => (int) $now->format('Ymd'),
                    'created_at' => $startedAt,
                ];
            }

            DB::table('playback_events')->insert($rows);
            unset($rows);
        }

        DB::table('device_heartbeats')->insert(
            $activeDevices->take(60)->flatMap(function (Device $device) use ($now) {
                return collect(range(1, 6))->map(fn (int $i) => [
                    'device_id' => $device->id,
                    'recorded_at' => $now->copy()->subMinutes($i * 10),
                    'app_version' => $device->app_version,
                    'available_storage' => $device->storage_free,
                    'manifest_version' => $device->current_manifest_version,
                    'player_status' => 'playing',
                    'network_status' => 'connected',
                    'created_at' => $now,
                ])->all();
            })->all(),
        );
    }

    protected function aggregate(): void
    {
        $aggregator = app(AggregateDailyAnalytics::class);

        for ($day = 0; $day <= 30; $day++) {
            $aggregator->handle(Carbon::today()->subDays($day));
        }
    }

    /**
     * @param  Collection<int, Device>  $devices
     */
    protected function seedAlerts($devices): void
    {
        $offline = $devices->filter(fn (Device $d) => $d->status === DeviceStatus::Offline)->take(3);

        foreach ($offline as $device) {
            Alert::query()->create([
                'type' => AlertType::DeviceOffline->value,
                'severity' => AlertSeverity::Critical->value,
                'status' => 'open',
                'title' => 'Pantalla desconectada',
                'message' => "«{$device->name}» lleva más de 30 minutos sin reportar.",
                'alertable_type' => $device->getMorphClass(),
                'alertable_id' => $device->id,
                'triggered_at' => now()->subMinutes(fake()->numberBetween(20, 240)),
            ]);
        }

        $lowStorage = $devices->take(2);
        foreach ($lowStorage as $device) {
            Alert::query()->create([
                'type' => AlertType::StorageLow->value,
                'severity' => AlertSeverity::Warning->value,
                'status' => 'open',
                'title' => 'Almacenamiento bajo',
                'message' => "La pantalla «{$device->name}» tiene menos del 10% de espacio libre.",
                'alertable_type' => $device->getMorphClass(),
                'alertable_id' => $device->id,
                'triggered_at' => now()->subHours(fake()->numberBetween(1, 12)),
            ]);
        }
    }

    protected function seedAudit(): void
    {
        $user = User::query()->value('id');

        $actions = [
            ['action' => 'campaign.published', 'entity_type' => 'App\\Domain\\Campaigns\\Models\\Campaign', 'entity_id' => 1],
            ['action' => 'business.created', 'entity_type' => 'App\\Domain\\Businesses\\Models\\Business', 'entity_id' => 4],
            ['action' => 'device.activated', 'entity_type' => 'App\\Domain\\Devices\\Models\\Device', 'entity_id' => 12],
            ['action' => 'layout.changed', 'entity_type' => 'App\\Domain\\Devices\\Models\\Device', 'entity_id' => 18],
            ['action' => 'device.command.issued', 'entity_type' => 'App\\Domain\\Devices\\Models\\Device', 'entity_id' => 22],
            ['action' => 'user.permissions.changed', 'entity_type' => 'App\\Domain\\Users\\Models\\Role', 'entity_id' => 3],
        ];

        foreach ($actions as $index => $entry) {
            AuditLog::query()->create([
                ...$entry,
                'user_id' => $user,
                'ip_address' => fake()->ipv4(),
                'user_agent' => 'Mozilla/5.0 (seed)',
                'created_at' => now()->subMinutes(($index + 1) * 37),
            ]);
        }
    }
}
