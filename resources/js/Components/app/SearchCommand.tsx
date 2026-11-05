import { router } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    CalendarClock,
    Image as ImageIcon,
    ListVideo,
    Loader2,
    MapPin,
    Megaphone,
    MonitorPlay,
    Search,
    Store,
    UserCog,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogTitle } from '@/Components/ui/dialog';
import { cn } from '@/Utils/cn';

interface SearchItem {
    id: number;
    title: string;
    subtitle: string | null;
    href: string;
}

interface SearchGroup {
    label: string;
    icon: string;
    items: SearchItem[];
}

const iconMap: Record<string, LucideIcon> = {
    business: Store,
    device: MonitorPlay,
    campaign: Megaphone,
    advertiser: Building2,
    location: MapPin,
    user: UserCog,
    media: ImageIcon,
    playlist: ListVideo,
    schedule: CalendarClock,
};

export function SearchCommand({
    endpoint = '/admin/search',
    placeholder = 'Buscar negocios, pantallas, campañas, anunciantes…',
}: {
    endpoint?: string;
    placeholder?: string;
} = {}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [groups, setGroups] = useState<SearchGroup[]>([]);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);

    const flat = groups.flatMap((group) => group.items);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setOpen((value) => !value);
            }
        };
        const onOpen = () => setOpen(true);
        window.addEventListener('keydown', onKey);
        window.addEventListener('signage:open-search', onOpen);
        return () => {
            window.removeEventListener('keydown', onKey);
            window.removeEventListener('signage:open-search', onOpen);
        };
    }, []);

    useEffect(() => {
        if (open) {
            setTimeout(() => inputRef.current?.focus(), 40);
        } else {
            setQuery('');
            setGroups([]);
            setActive(0);
        }
    }, [open]);

    useEffect(() => {
        if (query.trim().length < 2) {
            setGroups([]);
            return;
        }

        setLoading(true);
        const controller = new AbortController();
        const timeout = setTimeout(async () => {
            try {
                const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                });
                const data = (await response.json()) as { groups: SearchGroup[] };
                setGroups(data.groups ?? []);
                setActive(0);
            } catch {
                /* aborted */
            } finally {
                setLoading(false);
            }
        }, 220);

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    }, [query, endpoint]);

    const go = (item: SearchItem) => {
        setOpen(false);
        router.visit(item.href);
    };

    const onKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((value) => Math.min(value + 1, flat.length - 1));
        }
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((value) => Math.max(value - 1, 0));
        }
        if (event.key === 'Enter' && flat[active]) {
            event.preventDefault();
            go(flat[active]);
        }
    };

    let index = -1;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="max-w-xl p-0">
                <DialogTitle className="sr-only">Buscar en la red</DialogTitle>
                <div className="flex items-center gap-3 border-b border-line px-4 py-3">
                    <Search className="size-4 text-faint" />
                    <input
                        ref={inputRef}
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder={placeholder}
                        className="h-8 flex-1 bg-transparent text-sm text-fg placeholder:text-faint focus:outline-none"
                    />
                    {loading ? <Loader2 className="size-4 animate-spin text-faint" /> : null}
                    <kbd className="rounded border border-line bg-inset px-1.5 py-0.5 text-[10px] text-faint">
                        ESC
                    </kbd>
                </div>

                <div className="max-h-80 overflow-y-auto p-2">
                    {query.trim().length < 2 ? (
                        <p className="px-3 py-8 text-center text-xs text-muted">
                            Escribe al menos 2 caracteres para buscar en toda la red.
                        </p>
                    ) : flat.length === 0 && !loading ? (
                        <p className="px-3 py-8 text-center text-xs text-muted">
                            Sin resultados para «{query}».
                        </p>
                    ) : (
                        groups.map((group) => {
                            const GroupIcon = iconMap[group.icon] ?? Search;
                            return (
                                <div key={group.label} className="mb-2">
                                    <p className="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-faint">
                                        {group.label}
                                    </p>
                                    {group.items.map((item) => {
                                        index += 1;
                                        const isActive = index === active;
                                        return (
                                            <button
                                                key={`${group.label}-${item.id}`}
                                                type="button"
                                                onMouseEnter={() => setActive(flat.indexOf(item))}
                                                onClick={() => go(item)}
                                                className={cn(
                                                    'flex w-full items-center gap-3 rounded-control px-3 py-2 text-left transition-colors',
                                                    isActive ? 'bg-elevated' : 'hover:bg-surface',
                                                )}
                                            >
                                                <GroupIcon className="size-4 shrink-0 text-accent" />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm text-fg">
                                                        {item.title}
                                                    </span>
                                                    {item.subtitle ? (
                                                        <span className="block truncate text-xs text-muted">
                                                            {item.subtitle}
                                                        </span>
                                                    ) : null}
                                                </span>
                                                <ArrowRight className="size-3.5 shrink-0 text-faint" />
                                            </button>
                                        );
                                    })}
                                </div>
                            );
                        })
                    )}
                </div>

                <div className="flex items-center justify-between border-t border-line px-4 py-2 text-[11px] text-faint">
                    <span>↑ ↓ para navegar · Enter para abrir</span>
                    <span className="flex items-center gap-1">
                        <kbd className="rounded border border-line bg-inset px-1.5 py-0.5">Ctrl</kbd>
                        <kbd className="rounded border border-line bg-inset px-1.5 py-0.5">K</kbd>
                    </span>
                </div>
            </DialogContent>
        </Dialog>
    );
}
