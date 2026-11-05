import {
    CalendarDays,
    Image as ImageIcon,
    LayoutDashboard,
    MonitorPlay,
    Settings,
    Sparkles,
    Zap,
} from 'lucide-react';
import { SidebarShell, type SidebarNavItem } from '@/Components/app/SidebarShell';
import { usePermissions } from '@/Hooks/usePermissions';

interface BusinessItem extends SidebarNavItem {
    permissions?: string[];
}

const items: BusinessItem[] = [
    { label: 'Inicio', href: '/business/dashboard', icon: LayoutDashboard, exact: true, permissions: ['business.dashboard.view'] },
    { label: 'Biblioteca', href: '/business/library', icon: ImageIcon, permissions: ['business.media.view'] },
    { label: 'Reproducción inmediata', href: '/business/quick-play', icon: Zap, permissions: ['business.devices.view'] },
    { label: 'Pantallas', href: '/business/screens', icon: MonitorPlay, permissions: ['business.devices.view'] },
    { label: 'Programación', href: '/business/schedule', icon: CalendarDays, permissions: ['business.schedules.view'] },
    { label: 'Vista previa', href: '/business/preview', icon: Sparkles, permissions: ['business.devices.view'] },
    { label: 'Configuración', href: '/business/settings', icon: Settings, permissions: ['business.settings.view'] },
];

export function BusinessSidebar({ collapsed, className }: { collapsed: boolean; className?: string }) {
    const { canAny } = usePermissions();

    const visible = items.filter((item) => !item.permissions || canAny(...item.permissions));

    return (
        <SidebarShell
            brand={{ title: 'Alter', subtitle: 'Panel del negocio', href: '/business/dashboard' }}
            sections={[{ label: 'Mi negocio', items: visible }]}
            collapsed={collapsed}
            className={className}
            footer={
                <p className="text-[10px] font-semibold uppercase leading-relaxed tracking-[0.18em] text-faint">
                    Tu negocio,
                    <br />
                    en grandes
                    <br />
                    momentos
                </p>
            }
        />
    );
}
