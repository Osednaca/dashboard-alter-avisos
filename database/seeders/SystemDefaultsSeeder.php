<?php

namespace Database\Seeders;

use App\Domain\Media\Models\Layout;
use App\Domain\Operations\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class SystemDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLayouts();
        $this->seedSystemSettings();
    }

    /**
     * @return Collection<int, Layout>
     */
    protected function seedLayouts()
    {
        $definitions = [
            ['name' => 'Lateral 70 / 30', 'orientation' => 'landscape', 'business_percentage' => 70, 'advertising_percentage' => 30, 'is_default' => ! Layout::query()->where('is_default', true)->exists()],
            ['name' => 'Lateral 60 / 40', 'orientation' => 'landscape', 'business_percentage' => 60, 'advertising_percentage' => 40, 'is_default' => false],
            ['name' => 'Vertical 70 / 30', 'orientation' => 'portrait', 'business_percentage' => 70, 'advertising_percentage' => 30, 'is_default' => false],
            ['name' => 'Franja inferior 80 / 20', 'orientation' => 'landscape', 'business_percentage' => 80, 'advertising_percentage' => 20, 'is_default' => false],
        ];

        return collect($definitions)->map(fn (array $attributes) => Layout::query()->firstOrCreate(
            ['name' => $attributes['name']],
            [...$attributes, 'configuration' => [
                'audio_mode' => 'advertising',
                'business_area' => 'left',
                'advertising_area' => 'right',
                'ticker' => $attributes['advertising_percentage'] <= 20,
            ]],
        ));
    }

    protected function seedSystemSettings(): void
    {
        $settings = [
            ['key' => 'network.name', 'value' => 'Red Alter Colombia', 'group' => 'general', 'label' => 'Nombre de la red'],
            ['key' => 'network.default_timezone', 'value' => 'America/Bogota', 'group' => 'general', 'label' => 'Zona horaria por defecto'],
            ['key' => 'device.offline_after_minutes', 'value' => 15, 'group' => 'devices', 'label' => 'Minutos para marcar desconectada'],
            ['key' => 'device.heartbeat_retention_days', 'value' => 14, 'group' => 'devices', 'label' => 'Retención de latidos (días)'],
            ['key' => 'campaign.max_priority', 'value' => 10, 'group' => 'advertising', 'label' => 'Prioridad máxima'],
            ['key' => 'notifications.email', 'value' => true, 'group' => 'notifications', 'label' => 'Notificaciones por correo'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::query()->firstOrCreate(
                ['key' => $setting['key']],
                ['value' => ['data' => $setting['value']], 'group' => $setting['group'], 'label' => $setting['label']],
            );
        }
    }
}
