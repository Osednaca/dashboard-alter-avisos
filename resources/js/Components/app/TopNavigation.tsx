import { router, usePage } from '@inertiajs/react';
import {
    Bell,
    ChevronDown,
    Circle,
    LogOut,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    Search,
    Settings,
    ShieldCheck,
    UserCog,
} from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import type { PageProps } from '@/Types';
import { initialsFrom } from '@/Utils/format';
import { cn } from '@/Utils/cn';

export function TopNavigation({
    collapsed,
    onToggleSidebar,
    onOpenMobileNav,
}: {
    collapsed: boolean;
    onToggleSidebar: () => void;
    onOpenMobileNav: () => void;
}) {
    const { auth, app, notifications } = usePage<PageProps>().props;
    const user = auth.user;
    const unread = notifications?.unread ?? 0;

    return (
        <header className="sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-line bg-canvas/85 px-3 backdrop-blur-md sm:gap-3 sm:px-4 lg:px-6">
            <Button variant="ghost" size="icon" className="lg:hidden" onClick={onOpenMobileNav} aria-label="Abrir menú">
                <Menu className="size-5" />
            </Button>

            <Button
                variant="ghost"
                size="icon"
                className="hidden lg:inline-flex"
                onClick={onToggleSidebar}
                aria-label={collapsed ? 'Expandir menú' : 'Colapsar menú'}
            >
                {collapsed ? <PanelLeftOpen className="size-5" /> : <PanelLeftClose className="size-5" />}
            </Button>

            <button
                type="button"
                onClick={() => window.dispatchEvent(new Event('signage:open-search'))}
                className="group flex h-10 min-w-0 flex-1 items-center gap-2 rounded-control border border-line bg-surface px-3 text-left text-sm text-muted transition-colors hover:border-line-strong sm:gap-3 lg:max-w-xl"
            >
                <Search className="size-4 text-faint" />
                <span className="flex-1 truncate">Buscar negocios, pantallas, campañas o anunciantes…</span>
                <span className="hidden items-center gap-1 sm:flex">
                    <kbd className="rounded border border-line bg-inset px-1.5 py-0.5 text-[10px] text-faint">Ctrl</kbd>
                    <kbd className="rounded border border-line bg-inset px-1.5 py-0.5 text-[10px] text-faint">K</kbd>
                </span>
            </button>

            <div className="ml-auto flex shrink-0 items-center gap-2">
                <Badge tone={app.env === 'production' ? 'positive' : 'info'} className="hidden md:inline-flex">
                    <Circle className="size-1.5 fill-current" />
                    {app.env === 'production' ? 'Producción' : `Entorno ${app.env}`}
                </Badge>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="relative" aria-label="Notificaciones">
                            <Bell className="size-5" />
                            {unread > 0 ? (
                                <span className="absolute right-1.5 top-1.5 flex size-4 items-center justify-center rounded-full bg-danger text-[9px] font-bold text-white">
                                    {unread > 9 ? '9+' : unread}
                                </span>
                            ) : null}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-80 max-w-[calc(100vw-2rem)]">
                        <DropdownMenuLabel>Alertas recientes</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {notifications?.recent?.length ? (
                            notifications.recent.map((item) => (
                                <DropdownMenuItem key={item.id} asChild>
                                    <a href="/admin/alerts" className="flex items-start gap-3">
                                        <span
                                            className={cn(
                                                'mt-1 size-2 shrink-0 rounded-full',
                                                item.severity === 'danger'
                                                    ? 'bg-danger'
                                                    : item.severity === 'warning'
                                                      ? 'bg-warning'
                                                      : 'bg-info',
                                            )}
                                        />
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm text-fg">{item.title}</span>
                                            <span className="block truncate text-xs text-muted">{item.message}</span>
                                        </span>
                                    </a>
                                </DropdownMenuItem>
                            ))
                        ) : (
                            <p className="px-3 py-6 text-center text-xs text-muted">Sin alertas abiertas.</p>
                        )}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <a href="/admin/alerts" className="justify-center text-accent">
                                Ver todas las alertas
                            </a>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className="flex items-center gap-2 rounded-control border border-line bg-surface py-1.5 pl-1.5 pr-2.5 text-left transition-colors hover:border-line-strong"
                        >
                            <Avatar>
                                {user?.avatar_url ? <AvatarImage src={user.avatar_url} alt={user.name} /> : null}
                                <AvatarFallback>{initialsFrom(user?.name ?? 'AD')}</AvatarFallback>
                            </Avatar>
                            <span className="hidden min-w-0 max-w-40 sm:block">
                                <span className="block truncate text-xs font-medium text-fg">{user?.name}</span>
                                <span className="block truncate text-[10px] text-faint">
                                    {user?.job_title ?? user?.roles?.[0] ?? 'Administrador'}
                                </span>
                            </span>
                            <ChevronDown className="size-3.5 text-faint" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel className="normal-case tracking-normal">
                            <span className="block text-sm text-fg">{user?.name}</span>
                            <span className="block text-xs text-muted">{user?.email}</span>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <a href="/admin/settings">
                                <UserCog className="size-4" />
                                Perfil
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <a href="/admin/settings">
                                <Settings className="size-4" />
                                Configuración
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <a href="/admin/audit">
                                <ShieldCheck className="size-4" />
                                Auditoría
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="danger"
                            onSelect={() => router.post('/logout')}
                        >
                            <LogOut className="size-4" />
                            Cerrar sesión
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
