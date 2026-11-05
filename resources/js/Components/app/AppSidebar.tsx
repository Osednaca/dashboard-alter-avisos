import {
    BarChart3,
    Bell,
    Building2,
    Image as ImageIcon,
    LayoutDashboard,
    MapPin,
    Megaphone,
    MonitorPlay,
    ScrollText,
    Settings,
    Smartphone,
    Store,
    UserCog,
    Zap,
} from 'lucide-react';
import { SidebarShell, type SidebarNavItem } from '@/Components/app/SidebarShell';
import { usePermissions } from '@/Hooks/usePermissions';

interface AdminItem extends SidebarNavItem {
    permissions?: string[];
}

const sections: Array<{ label: string; items: AdminItem[] }> = [
    {
        label: 'Operación',
        items: [
            { label: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard, exact: true },
            { label: 'Negocios', href: '/admin/businesses', icon: Store, permissions: ['businesses.view'] },
            { label: 'Ubicaciones', href: '/admin/locations', icon: MapPin, permissions: ['locations.view'] },
            {
                label: 'Pantallas',
                href: '/admin/devices',
                icon: MonitorPlay,
                exact: true,
                permissions: ['devices.view'],
                children: [
                    { label: 'Todas las pantallas', href: '/admin/devices' },
                    { label: 'Activaciones', href: '/admin/devices/activations' },
                ],
            },
            { label: 'Reproducción inmediata', href: '/admin/quick-play', icon: Zap, permissions: ['quick_play.view'] },
            { label: 'Campañas', href: '/admin/campaigns', icon: Megaphone, permissions: ['campaigns.view'] },
            { label: 'Creatividades', href: '/admin/creatives', icon: ImageIcon, permissions: ['creatives.view'] },
            { label: 'Anunciantes', href: '/admin/advertisers', icon: Building2, permissions: ['advertisers.view'] },
        ],
    },
    {
        label: 'Inteligencia',
        items: [
            { label: 'Analíticas', href: '/admin/analytics', icon: BarChart3, permissions: ['analytics.view'] },
            { label: 'Alertas', href: '/admin/alerts', icon: Bell, permissions: ['alerts.manage', 'devices.view'] },
        ],
    },
    {
        label: 'Administración',
        items: [
            { label: 'Usuarios', href: '/admin/users', icon: UserCog, permissions: ['users.manage'] },
            { label: 'Auditoría', href: '/admin/audit', icon: ScrollText, permissions: ['audit.view'] },
            { label: 'Actualizaciones Android', href: '/admin/android-updates', icon: Smartphone, permissions: ['system.settings'] },
            { label: 'Configuración', href: '/admin/settings', icon: Settings, permissions: ['system.settings', 'roles.manage'] },
        ],
    },
];

export function AppSidebar({ collapsed, className }: { collapsed: boolean; className?: string }) {
    const { canAny } = usePermissions();

    const visibleSections = sections
        .map((section) => ({
            label: section.label,
            items: section.items.filter((item) => !item.permissions || canAny(...item.permissions)),
        }))
        .filter((section) => section.items.length > 0);

    return (
        <SidebarShell
            brand={{ title: 'Alter', subtitle: 'Red de pantallas', href: '/admin/dashboard' }}
            sections={visibleSections}
            collapsed={collapsed}
            className={className}
            footer={
                <p className="text-[10px] font-semibold uppercase leading-relaxed tracking-[0.18em] text-faint">
                    Negocios
                    <br />
                    Pantallas
                    <br />
                    Audiencias
                </p>
            }
        />
    );
}
