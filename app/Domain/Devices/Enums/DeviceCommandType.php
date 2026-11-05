<?php

namespace App\Domain\Devices\Enums;

enum DeviceCommandType: string
{
    case SyncContent = 'SYNC_CONTENT';
    case RefreshManifest = 'REFRESH_MANIFEST';
    case ClearCache = 'CLEAR_CACHE';
    case RestartPlayer = 'RESTART_PLAYER';
    case DownloadContent = 'DOWNLOAD_CONTENT';
    case ChangeLayout = 'CHANGE_LAYOUT';
    case Mute = 'MUTE';
    case Unmute = 'UNMUTE';
    case TakeScreenshot = 'TAKE_SCREENSHOT';
    case ReloadApplication = 'RELOAD_APPLICATION';
    case QuickPlay = 'QUICK_PLAY';

    public function label(): string
    {
        return match ($this) {
            self::SyncContent => 'Sincronizar contenido',
            self::RefreshManifest => 'Actualizar manifiesto',
            self::ClearCache => 'Limpiar caché',
            self::RestartPlayer => 'Reiniciar reproductor',
            self::DownloadContent => 'Descargar contenido',
            self::ChangeLayout => 'Cambiar layout',
            self::Mute => 'Silenciar',
            self::Unmute => 'Activar sonido',
            self::TakeScreenshot => 'Capturar pantalla',
            self::ReloadApplication => 'Recargar aplicación',
            self::QuickPlay => 'Reproducción inmediata',
        };
    }

    public function requiresConfirmation(): bool
    {
        return match ($this) {
            self::ClearCache, self::RestartPlayer, self::ReloadApplication, self::ChangeLayout => true,
            default => false,
        };
    }
}
