<?php

namespace App\Domain\Operations\Enums;

enum AlertType: string
{
    case DeviceOffline = 'device_offline';
    case StorageLow = 'storage_low';
    case MediaDownloadFailed = 'media_download_failed';
    case CampaignWithoutCreatives = 'campaign_without_creatives';
    case CampaignWithoutTargets = 'campaign_without_targets';
    case RepeatedPlaybackErrors = 'repeated_playback_errors';
    case SynchronizationFailed = 'sync_failed';

    public function label(): string
    {
        return match ($this) {
            self::DeviceOffline => 'Pantalla desconectada',
            self::StorageLow => 'Almacenamiento bajo',
            self::MediaDownloadFailed => 'Fallo de descarga de contenido',
            self::CampaignWithoutCreatives => 'Campaña sin creatividades',
            self::CampaignWithoutTargets => 'Campaña sin pantallas objetivo',
            self::RepeatedPlaybackErrors => 'Errores de reproducción repetidos',
            self::SynchronizationFailed => 'Sincronización fallida',
        };
    }
}
