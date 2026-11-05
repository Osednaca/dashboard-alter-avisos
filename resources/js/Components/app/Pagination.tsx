import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import type { Paginated } from '@/Types';

export function Pagination<T>({ paginator, onNavigate }: { paginator: Paginated<T>; onNavigate?: (url: string) => void }) {
    if (paginator.last_page <= 1) return null;

    return (
        <div className="flex flex-col gap-3 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs text-muted">
                Mostrando <span className="text-fg">{paginator.from}</span>–
                <span className="text-fg">{paginator.to}</span> de{' '}
                <span className="text-fg">{paginator.total}</span>
            </p>
            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    asChild
                    className={!paginator.links[0]?.url ? 'pointer-events-none opacity-40' : ''}
                >
                    <a href={paginator.links[0]?.url ?? '#'} onClick={(event) => { const url = paginator.links[0]?.url; if (onNavigate && url) { event.preventDefault(); onNavigate(url); } }}>
                        <ChevronLeft className="size-3.5" />
                        Anterior
                    </a>
                </Button>
                <span className="metric text-xs text-muted">
                    {paginator.current_page} / {paginator.last_page}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    asChild
                    className={
                        !paginator.links[paginator.links.length - 1]?.url
                            ? 'pointer-events-none opacity-40'
                            : ''
                    }
                >
                    <a href={paginator.links[paginator.links.length - 1]?.url ?? '#'} onClick={(event) => { const url = paginator.links[paginator.links.length - 1]?.url; if (onNavigate && url) { event.preventDefault(); onNavigate(url); } }}>
                        Siguiente
                        <ChevronRight className="size-3.5" />
                    </a>
                </Button>
            </div>
        </div>
    );
}
