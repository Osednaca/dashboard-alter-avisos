<?php

namespace App\Domain\Users\Enums;

enum PermissionEnum: string
{
    case BusinessesView = 'businesses.view';
    case BusinessesCreate = 'businesses.create';
    case BusinessesEdit = 'businesses.edit';
    case BusinessesDelete = 'businesses.delete';

    case LocationsView = 'locations.view';
    case LocationsManage = 'locations.manage';

    case DevicesView = 'devices.view';
    case DevicesManage = 'devices.manage';
    case DevicesCommands = 'devices.commands';

    case LayoutsManage = 'layouts.manage';
    case PlaylistsManage = 'playlists.manage';

    case QuickPlayView = 'quick_play.view';
    case QuickPlaySend = 'quick_play.send';

    case BusinessDashboardView = 'business.dashboard.view';
    case BusinessMediaView = 'business.media.view';
    case BusinessMediaUpload = 'business.media.upload';
    case BusinessMediaDelete = 'business.media.delete';
    case BusinessPlaylistsView = 'business.playlists.view';
    case BusinessPlaylistsManage = 'business.playlists.manage';
    case BusinessSchedulesView = 'business.schedules.view';
    case BusinessSchedulesManage = 'business.schedules.manage';
    case BusinessDevicesView = 'business.devices.view';
    case BusinessDevicesSync = 'business.devices.sync';
    case BusinessReportsView = 'business.reports.view';
    case BusinessSettingsView = 'business.settings.view';
    case BusinessSettingsManage = 'business.settings.manage';

    case CreativesView = 'creatives.view';
    case CreativesManage = 'creatives.manage';

    case CampaignsView = 'campaigns.view';
    case CampaignsCreate = 'campaigns.create';
    case CampaignsEdit = 'campaigns.edit';
    case CampaignsPublish = 'campaigns.publish';
    case CampaignsDelete = 'campaigns.delete';

    case AdvertisersView = 'advertisers.view';
    case AdvertisersManage = 'advertisers.manage';

    case AnalyticsView = 'analytics.view';

    case AlertsManage = 'alerts.manage';
    case AuditView = 'audit.view';

    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case SystemSettings = 'system.settings';

    public function label(): string
    {
        return match ($this) {
            self::BusinessesView => 'Ver negocios',
            self::BusinessesCreate => 'Crear negocios',
            self::BusinessesEdit => 'Editar negocios',
            self::BusinessesDelete => 'Eliminar negocios',
            self::LocationsView => 'Ver ubicaciones',
            self::LocationsManage => 'Gestionar ubicaciones',
            self::DevicesView => 'Ver pantallas',
            self::DevicesManage => 'Gestionar pantallas',
            self::DevicesCommands => 'Enviar comandos a pantallas',
            self::LayoutsManage => 'Gestionar layouts',
            self::PlaylistsManage => 'Gestionar listas de reproducción',
            self::QuickPlayView => 'Ver reproducción inmediata',
            self::QuickPlaySend => 'Enviar reproducción inmediata',
            self::BusinessDashboardView => 'Ver el panel del negocio',
            self::BusinessMediaView => 'Ver biblioteca del negocio',
            self::BusinessMediaUpload => 'Subir contenido del negocio',
            self::BusinessMediaDelete => 'Eliminar contenido del negocio',
            self::BusinessPlaylistsView => 'Ver listas de reproducción',
            self::BusinessPlaylistsManage => 'Gestionar listas de reproducción',
            self::BusinessSchedulesView => 'Ver programación',
            self::BusinessSchedulesManage => 'Gestionar programación',
            self::BusinessDevicesView => 'Ver pantallas del negocio',
            self::BusinessDevicesSync => 'Sincronizar pantallas del negocio',
            self::BusinessReportsView => 'Ver reportes del negocio',
            self::BusinessSettingsView => 'Ver configuración del negocio',
            self::BusinessSettingsManage => 'Editar configuración del negocio',
            self::CreativesView => 'Ver creatividades',
            self::CreativesManage => 'Gestionar creatividades',
            self::CampaignsView => 'Ver campañas',
            self::CampaignsCreate => 'Crear campañas',
            self::CampaignsEdit => 'Editar campañas',
            self::CampaignsPublish => 'Publicar campañas',
            self::CampaignsDelete => 'Eliminar campañas',
            self::AdvertisersView => 'Ver anunciantes',
            self::AdvertisersManage => 'Gestionar anunciantes',
            self::AnalyticsView => 'Ver analíticas',
            self::AlertsManage => 'Gestionar alertas',
            self::AuditView => 'Ver auditoría',
            self::UsersManage => 'Gestionar usuarios',
            self::RolesManage => 'Gestionar roles y permisos',
            self::SystemSettings => 'Configuración del sistema',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::BusinessesView, self::BusinessesCreate, self::BusinessesEdit,
            self::BusinessesDelete, self::LocationsView, self::LocationsManage => 'Negocios',
            self::DevicesView, self::DevicesManage, self::DevicesCommands,
            self::LayoutsManage => 'Pantallas',
            self::PlaylistsManage, self::CreativesView, self::CreativesManage => 'Contenido',
            self::CampaignsView, self::CampaignsCreate, self::CampaignsEdit,
            self::CampaignsPublish, self::CampaignsDelete, self::AdvertisersView,
            self::AdvertisersManage => 'Publicidad',
            self::AnalyticsView => 'Datos',
            self::QuickPlayView, self::QuickPlaySend,
            self::AlertsManage, self::AuditView => 'Operación',
            self::BusinessDashboardView, self::BusinessMediaView, self::BusinessMediaUpload,
            self::BusinessMediaDelete, self::BusinessPlaylistsView, self::BusinessPlaylistsManage,
            self::BusinessSchedulesView, self::BusinessSchedulesManage, self::BusinessDevicesView,
            self::BusinessDevicesSync, self::BusinessReportsView, self::BusinessSettingsView,
            self::BusinessSettingsManage => 'Negocio',
            self::UsersManage, self::RolesManage, self::SystemSettings => 'Administración',
        };
    }

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = [
                'value' => $permission->value,
                'label' => $permission->label(),
            ];
        }

        return $groups;
    }
}
