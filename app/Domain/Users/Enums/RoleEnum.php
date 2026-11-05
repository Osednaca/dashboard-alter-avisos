<?php

namespace App\Domain\Users\Enums;

enum RoleEnum: string
{
    case SuperAdmin = 'super-admin';
    case Administrator = 'administrator';
    case Operator = 'operator';
    case CampaignManager = 'campaign-manager';
    case Support = 'support';
    case BusinessUser = 'business-user';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrador',
            self::Administrator => 'Administrador',
            self::Operator => 'Operador',
            self::CampaignManager => 'Gestor de campañas',
            self::Support => 'Soporte',
            self::BusinessUser => 'Usuario de negocio',
        };
    }

    /**
     * @return array<int, PermissionEnum>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin, self::Administrator => PermissionEnum::cases(),
            self::Operator => [
                PermissionEnum::BusinessesView,
                PermissionEnum::LocationsView,
                PermissionEnum::DevicesView,
                PermissionEnum::DevicesManage,
                PermissionEnum::DevicesCommands,
                PermissionEnum::LayoutsManage,
                PermissionEnum::PlaylistsManage,
                PermissionEnum::QuickPlayView,
                PermissionEnum::QuickPlaySend,
                PermissionEnum::CreativesView,
                PermissionEnum::AnalyticsView,
                PermissionEnum::AlertsManage,
            ],
            self::CampaignManager => [
                PermissionEnum::BusinessesView,
                PermissionEnum::DevicesView,
                PermissionEnum::CampaignsView,
                PermissionEnum::CampaignsCreate,
                PermissionEnum::CampaignsEdit,
                PermissionEnum::CampaignsPublish,
                PermissionEnum::AdvertisersView,
                PermissionEnum::AdvertisersManage,
                PermissionEnum::CreativesView,
                PermissionEnum::CreativesManage,
                PermissionEnum::PlaylistsManage,
                PermissionEnum::QuickPlayView,
                PermissionEnum::QuickPlaySend,
                PermissionEnum::AnalyticsView,
            ],
            self::Support => [
                PermissionEnum::BusinessesView,
                PermissionEnum::LocationsView,
                PermissionEnum::DevicesView,
                PermissionEnum::CampaignsView,
                PermissionEnum::AdvertisersView,
                PermissionEnum::AnalyticsView,
                PermissionEnum::QuickPlayView,
                PermissionEnum::AlertsManage,
            ],
            self::BusinessUser => [
                PermissionEnum::BusinessDashboardView,
                PermissionEnum::BusinessMediaView,
                PermissionEnum::BusinessMediaUpload,
                PermissionEnum::BusinessMediaDelete,
                PermissionEnum::BusinessPlaylistsView,
                PermissionEnum::BusinessPlaylistsManage,
                PermissionEnum::BusinessSchedulesView,
                PermissionEnum::BusinessSchedulesManage,
                PermissionEnum::BusinessDevicesView,
                PermissionEnum::BusinessDevicesSync,
                PermissionEnum::BusinessReportsView,
                PermissionEnum::BusinessSettingsView,
                PermissionEnum::BusinessSettingsManage,
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public function permissionValues(): array
    {
        return array_map(fn (PermissionEnum $p) => $p->value, $this->permissions());
    }
}
