import { usePage } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { SearchCommand } from '@/Components/app/SearchCommand';
import { BrandLogo } from '@/Components/app/BrandLogo';
import { TooltipProvider } from '@/Components/ui/tooltip';
import type { PageProps } from '@/Types';
import { storageGet, storageSet } from '@/Utils/storage';
import { cn } from '@/Utils/cn';

interface TopNavRenderArgs {
    collapsed: boolean;
    onToggleSidebar: () => void;
    onOpenMobileNav: () => void;
}

/**
 * Shared application shell used by both the admin and business dashboards:
 * collapsible sidebar, sticky top navigation, mobile drawer, flash toasts and
 * an optional command palette. Each area supplies its own navigation.
 */
export function DashboardLayout({
    children,
    header,
    contentClassName,
    footer,
    sidebar,
    topNavigation,
    searchCommand,
    searchCommandProps,
    storageKey = 'signage:sidebar:v1',
}: {
    children: ReactNode;
    header?: ReactNode;
    contentClassName?: string;
    footer?: ReactNode;
    sidebar: (opts: { collapsed: boolean; mobile: boolean }) => ReactNode;
    topNavigation: (opts: TopNavRenderArgs) => ReactNode;
    searchCommand?: boolean;
    searchCommandProps?: { endpoint?: string; placeholder?: string };
    storageKey?: string;
}) {
    const { flash, app } = usePage<PageProps>().props;
    const [collapsed, setCollapsed] = useState(() => storageGet(storageKey, false));
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        storageSet(storageKey, collapsed);
    }, [collapsed, storageKey]);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.info) toast.info(flash.info);
    }, [flash?.success, flash?.error, flash?.info]);

    return (
        <TooltipProvider delayDuration={200}>
            <div className="min-h-screen bg-canvas">
                {sidebar({ collapsed, mobile: false })}

                {mobileOpen ? (
                    <div className="fixed inset-0 z-50 lg:hidden">
                        <button
                            type="button"
                            aria-label="Cerrar menú"
                            className="absolute inset-0 bg-black/70"
                            onClick={() => setMobileOpen(false)}
                        />
                        {sidebar({ collapsed: false, mobile: true })}
                    </div>
                ) : null}

                <div
                    className={cn(
                        'flex min-h-screen min-w-0 flex-col transition-[padding] duration-200',
                        collapsed ? 'lg:pl-[76px]' : 'lg:pl-64',
                    )}
                >
                    {topNavigation({
                        collapsed,
                        onToggleSidebar: () => setCollapsed((value) => !value),
                        onOpenMobileNav: () => setMobileOpen(true),
                    })}

                    <main className={cn('min-w-0 flex-1 px-4 py-5 sm:px-6 sm:py-8', contentClassName)}>
                        {header ? <div className="mb-6">{header}</div> : null}
                        {children}
                    </main>

                    <footer className="border-t border-line px-4 py-4 text-[11px] text-faint lg:px-6">
                        {footer ?? (
                            <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <span className="flex items-center gap-2">
                                    <BrandLogo decorative className="size-6" />
                                    <span>{app.name} · Plataforma de señalización digital y red publicitaria</span>
                                </span>
                                <span className="metric">v1.0.0</span>
                            </div>
                        )}
                    </footer>
                </div>

                {searchCommand ? <SearchCommand {...searchCommandProps} /> : null}
            </div>
        </TooltipProvider>
    );
}
