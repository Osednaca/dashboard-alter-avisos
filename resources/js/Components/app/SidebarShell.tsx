import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, type LucideIcon } from 'lucide-react';
import { BrandLogo } from '@/Components/app/BrandLogo';
import { useState, type ReactNode } from 'react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/Components/ui/tooltip';
import { cn } from '@/Utils/cn';

export interface SidebarNavChild {
    label: string;
    href: string;
}

export interface SidebarNavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    exact?: boolean;
    children?: SidebarNavChild[];
}

export interface SidebarNavSection {
    label: string;
    items: SidebarNavItem[];
}

export function SidebarShell({
    brand,
    sections,
    collapsed,
    className,
    footer,
}: {
    brand: { title: string; subtitle: string; href: string; icon?: LucideIcon };
    sections: SidebarNavSection[];
    collapsed: boolean;
    className?: string;
    footer?: ReactNode;
}) {
    const { url } = usePage();
    const pathname = url.split('?')[0];

    const [expanded, setExpanded] = useState<string | null>(() => {
        const match = sections
            .flatMap((section) => section.items)
            .find((item) => item.children?.length && pathname.startsWith(item.href));

        return match?.href ?? null;
    });

    const isActive = (item: SidebarNavItem) =>
        item.exact
            ? pathname === item.href
            : pathname === item.href || pathname.startsWith(`${item.href}/`);

    return (
        <aside
            className={cn(
                'fixed inset-y-0 left-0 z-40 hidden flex-col border-r border-line bg-surface transition-[width] duration-200 lg:flex',
                collapsed ? 'w-[76px]' : 'w-64',
                className,
            )}
        >
            <Link
                href={brand.href}
                aria-label={`${brand.title} · ${brand.subtitle}`}
                className={cn(
                    'flex h-16 items-center gap-3 border-b border-line px-4',
                    collapsed && 'justify-center px-0',
                )}
            >
                <BrandLogo decorative />
                {!collapsed ? (
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold tracking-tight text-fg">{brand.title}</p>
                        <p className="truncate text-[10px] uppercase tracking-wider text-faint">{brand.subtitle}</p>
                    </div>
                ) : null}
            </Link>

            <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-4 scrollbar-none">
                {sections.map((section) => {
                    if (section.items.length === 0) return null;

                    return (
                        <div key={section.label}>
                            {!collapsed ? (
                                <p className="mb-2 px-2 text-[10px] font-semibold uppercase tracking-wider text-faint">
                                    {section.label}
                                </p>
                            ) : null}
                            <ul className="space-y-1">
                                {section.items.map((item) => {
                                    const Icon = item.icon;
                                    const active = isActive(item);
                                    const hasChildren = !!item.children?.length;
                                    const isExpanded = expanded === item.href;

                                    const classes = cn(
                                        'group relative flex w-full items-center gap-3 rounded-control px-3 py-2.5 text-sm transition-colors',
                                        active ? 'bg-accent/10 text-fg' : 'text-muted hover:bg-elevated hover:text-fg',
                                        collapsed && 'justify-center px-0',
                                    );

                                    const content: ReactNode = (
                                        <>
                                            {active ? (
                                                <span className="absolute left-0 top-1/2 h-6 w-0.5 -translate-y-1/2 rounded-r bg-accent" />
                                            ) : null}
                                            <Icon
                                                className={cn(
                                                    'size-[18px] shrink-0',
                                                    active ? 'text-accent' : 'text-faint group-hover:text-muted',
                                                )}
                                            />
                                            {!collapsed ? (
                                                <>
                                                    <span className="flex-1 truncate text-left">{item.label}</span>
                                                    {hasChildren ? (
                                                        <ChevronRight
                                                            className={cn(
                                                                'size-3.5 text-faint transition-transform',
                                                                isExpanded && 'rotate-90',
                                                            )}
                                                        />
                                                    ) : null}
                                                </>
                                            ) : null}
                                        </>
                                    );

                                    return (
                                        <li key={item.href}>
                                            {hasChildren && !collapsed ? (
                                                <button
                                                    type="button"
                                                    onClick={() => setExpanded(isExpanded ? null : item.href)}
                                                    aria-expanded={isExpanded}
                                                    className={classes}
                                                >
                                                    {content}
                                                </button>
                                            ) : collapsed ? (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Link href={item.href} className={classes}>
                                                            {content}
                                                        </Link>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="right">{item.label}</TooltipContent>
                                                </Tooltip>
                                            ) : (
                                                <Link href={item.href} className={classes}>
                                                    {content}
                                                </Link>
                                            )}

                                            {hasChildren && isExpanded && !collapsed ? (
                                                <ul className="mt-1 space-y-1 border-l border-line pl-4">
                                                    {item.children!.map((child) => (
                                                        <li key={child.href}>
                                                            <Link
                                                                href={child.href}
                                                                className={cn(
                                                                    'block rounded-control px-3 py-1.5 text-xs transition-colors',
                                                                    pathname === child.href
                                                                        ? 'bg-elevated text-accent'
                                                                        : 'text-muted hover:text-fg',
                                                                )}
                                                            >
                                                                {child.label}
                                                            </Link>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : null}
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    );
                })}
            </nav>

            {!collapsed && footer ? (
                <div className="border-t border-line px-4 py-4">{footer}</div>
            ) : null}
        </aside>
    );
}
