<?php

namespace Database\Seeders;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Enums\ProcessingStatus;
use App\Domain\Media\Models\Layout;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Playlists\Enums\PlaylistStatus;
use App\Domain\Playlists\Enums\PlaylistType;
use App\Domain\Playlists\Models\Playlist;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A realistic restaurant business used to demo the business dashboard:
 * La Hamburguesería with three locations, three screens, its own library,
 * playlists and daily schedules.
 */
class BusinessDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Los datos demo solo se permiten en local/testing. Usa php artisan db:seed --force y php artisan signage:admin.');
        }

        $layout = Layout::query()->where('is_default', true)->first()
            ?? Layout::query()->firstOrCreate(
                ['name' => 'Lateral 70 / 30'],
                [
                    'orientation' => 'landscape',
                    'business_percentage' => 70,
                    'advertising_percentage' => 30,
                    'is_default' => true,
                ],
            );

        $business = Business::query()->updateOrCreate(
            ['slug' => 'la-hamburgueseria'],
            [
                'name' => 'La Hamburguesería',
                'category' => 'restaurant',
                'status' => 'active',
                'timezone' => 'America/Bogota',
                'contact_name' => 'Carlos Rodríguez',
                'contact_email' => 'carlos@lahamburgueseria.co',
                'contact_phone' => '+57 300 123 4567',
                'metadata' => ['audio_volume' => 70, 'notify_email' => true, 'notify_offline' => true],
            ],
        );

        $locations = collect([
            ['name' => 'El Poblado', 'city' => 'Medellín', 'state' => 'Antioquia', 'address' => 'Cra 43A #1-50'],
            ['name' => 'Laureles', 'city' => 'Medellín', 'state' => 'Antioquia', 'address' => 'Cir 74 #34-10'],
            ['name' => 'Envigado', 'city' => 'Envigado', 'state' => 'Antioquia', 'address' => 'Cra 43A #30-20'],
        ])->mapWithKeys(fn (array $attributes) => [
            $attributes['name'] => $business->locations()->updateOrCreate(
                ['name' => $attributes['name']],
                [...$attributes, 'country' => 'Colombia', 'timezone' => 'America/Bogota', 'status' => 'active'],
            ),
        ]);

        $devices = collect([
            ['name' => 'TV Entrada', 'location' => 'El Poblado', 'status' => DeviceStatus::Online],
            ['name' => 'TV Caja', 'location' => 'Laureles', 'status' => DeviceStatus::Online],
            ['name' => 'TV Terraza', 'location' => 'Envigado', 'status' => DeviceStatus::Offline],
        ])->map(fn (array $attributes) => Device::query()->updateOrCreate(
            ['name' => $attributes['name'], 'business_id' => $business->id],
            [
                'location_id' => $locations[$attributes['location']]->id,
                'uuid' => (string) Str::uuid(),
                'activation_code' => strtoupper(Str::random(6)),
                'status' => $attributes['status'],
                'app_version' => '1.6.2',
                'last_seen_at' => $attributes['status'] === DeviceStatus::Online ? now()->subMinutes(3) : now()->subHours(4),
                'last_ip' => '190.0.'.random_int(0, 255).'.'.random_int(1, 254),
                'storage_total' => 32 * 1024 * 1024 * 1024,
                'storage_free' => random_int(6, 22) * 1024 * 1024 * 1024,
                'current_manifest_version' => now()->subMinutes(12)->format('YmdHis'),
                'last_sync_at' => now()->subMinutes(12),
                'current_layout_id' => $layout->id,
            ],
        ));

        $media = $this->seedMedia($business, $layout);

        $playlists = $this->seedPlaylists($business, $media);

        $devices->each(fn (Device $device) => $device->forceFill([
            'current_playlist_id' => $playlists['Menú Principal']->id,
        ])->save());

        $this->seedSchedules($business, $playlists, $locations);
        $this->seedUser($business);
    }

    /**
     * @return Collection<string, MediaAsset>
     */
    protected function seedMedia(Business $business, Layout $layout): Collection
    {
        $items = [
            ['Hamburguesa Clásica', 'image', 10],
            ['Combo del Día', 'video', 20],
            ['Bebidas Naturales', 'image', 10],
            ['Papas Crocantes', 'image', 10],
            ['Postres', 'image', 10],
            ['Promoción 2x1', 'video', 20],
            ['Bienvenida', 'image', 10],
            ['Menú Desayuno', 'image', 12],
            ['Happy Hour', 'video', 18],
            ['Menú Cena', 'image', 12],
        ];

        return collect($items)->mapWithKeys(function (array $item) use ($business) {
            [$name, $type, $duration] = $item;
            $isVideo = $type === 'video';
            $filename = $name.($isVideo ? '.mp4' : '.jpg');

            $asset = $business->mediaAssets()->updateOrCreate(
                ['filename' => $filename],
                [
                    'type' => $type,
                    'original_name' => $filename,
                    'storage_path' => 'media/'.$type.'/'.now()->format('Y/m').'/'.Str::slug($name).($isVideo ? '.mp4' : '.jpg'),
                    'thumbnail_path' => $isVideo ? null : 'media/image/'.now()->format('Y/m').'/'.Str::slug($name).'.jpg',
                    'mime_type' => $isVideo ? 'video/mp4' : 'image/jpeg',
                    'width' => 1920,
                    'height' => 1080,
                    'duration' => $isVideo ? $duration : null,
                    'filesize' => random_int(300_000, 18_000_000),
                    'checksum' => hash('sha256', $filename),
                    'processing_status' => ProcessingStatus::Ready,
                    'metadata' => ['seeded' => true],
                ],
            );

            return [$name => $asset];
        });
    }

    /**
     * @param  Collection<string, MediaAsset>  $media
     * @return array<string, Playlist>
     */
    protected function seedPlaylists(Business $business, Collection $media): array
    {
        $definitions = [
            'Menú Principal' => ['Hamburguesa Clásica', 'Combo del Día', 'Bebidas Naturales', 'Postres', 'Promoción 2x1', 'Bienvenida'],
            'Menú Desayuno' => ['Menú Desayuno', 'Bebidas Naturales', 'Bienvenida'],
            'Happy Hour' => ['Happy Hour', 'Bebidas Naturales', 'Papas Crocantes'],
            'Menú Cena' => ['Menú Cena', 'Hamburguesa Clásica', 'Postres'],
        ];

        $playlists = [];

        foreach ($definitions as $name => $names) {
            $playlist = $business->playlists()->updateOrCreate(
                ['name' => $name],
                ['type' => PlaylistType::Business, 'status' => PlaylistStatus::Active],
            );

            $playlist->items()->delete();

            foreach (array_values($names) as $index => $mediaName) {
                $asset = $media[$mediaName] ?? null;
                if (! $asset) {
                    continue;
                }

                $playlist->items()->create([
                    'media_asset_id' => $asset->id,
                    'sort_order' => $index,
                    'duration' => $asset->duration ?? 10,
                    'transition' => $index % 2 === 0 ? 'fade' : 'slide_left',
                ]);
            }

            $playlists[$name] = $playlist;
        }

        return $playlists;
    }

    /**
     * @param  array<string, Playlist>  $playlists
     * @param  Collection<string, Location>  $locations
     */
    protected function seedSchedules(Business $business, array $playlists, Collection $locations): void
    {
        $definitions = [
            ['name' => 'Desayuno', 'playlist' => 'Menú Desayuno', 'start' => '06:00', 'end' => '11:00'],
            ['name' => 'Almuerzo', 'playlist' => 'Menú Principal', 'start' => '11:00', 'end' => '16:00'],
            ['name' => 'Happy Hour', 'playlist' => 'Happy Hour', 'start' => '16:00', 'end' => '20:00'],
            ['name' => 'Cena', 'playlist' => 'Menú Cena', 'start' => '20:00', 'end' => '23:59'],
        ];

        foreach ($definitions as $definition) {
            $business->schedules()->updateOrCreate(
                ['name' => $definition['name']],
                [
                    'location_id' => null,
                    'playlist_id' => $playlists[$definition['playlist']]->id,
                    'daily_start_time' => $definition['start'].':00',
                    'daily_end_time' => $definition['end'].':00',
                    'days_of_week' => [],
                    'priority' => 0,
                    'status' => 'active',
                ],
            );
        }
    }

    protected function seedUser(Business $business): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'carlos@lahamburgueseria.co'],
            [
                'name' => 'Carlos Rodríguez',
                'job_title' => 'Propietario',
                'password' => 'password',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles(['business-user']);

        $business->users()->syncWithoutDetaching([
            $user->id => ['role' => 'owner', 'is_primary' => true],
        ]);
    }
}
