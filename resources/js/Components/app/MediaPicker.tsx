import axios from 'axios';
import { Check, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { MediaThumbnail } from '@/Components/app/MediaThumbnail';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import type { MediaEntity, Paginated } from '@/Types';
import { cn } from '@/Utils/cn';

export function MediaPicker({
    open,
    onOpenChange,
    media,
    selectedIds = [],
    onSelect,
    title = 'Seleccionar contenido',
    endpoint,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    media: MediaEntity[];
    selectedIds?: number[];
    onSelect: (media: MediaEntity) => void;
    title?: string;
    endpoint?: string;
}) {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [result, setResult] = useState<Paginated<MediaEntity> | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [retry, setRetry] = useState(0);
    useEffect(() => {
        if (!endpoint || !open) return;
        const controller = new AbortController();
        setLoading(true);
        setError('');
        const timer = setTimeout(async () => {
            try {
                const response = await axios.get<{ media: Paginated<MediaEntity> }>(endpoint, { params: { search, page }, signal: controller.signal });
                if (!controller.signal.aborted) setResult(response.data.media);
            } catch {
                if (!controller.signal.aborted) setError('No se pudo cargar la biblioteca. Reintenta sin cerrar tu programación.');
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 250);
        return () => { clearTimeout(timer); controller.abort(); };
    }, [endpoint, open, search, page, retry]);

    const filtered = useMemo(() => {
        if (endpoint) return result?.data ?? [];
        const term = search.trim().toLowerCase();
        if (!term) return media;
        return media.filter((asset) => asset.filename.toLowerCase().includes(term));
    }, [media, search, endpoint, result]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>

                <div className="relative mb-3">
                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-faint" />
                    <Input
                        value={search}
                        onChange={(event) => { setSearch(event.target.value); setPage(1); }}
                        placeholder="Buscar en la biblioteca…"
                        aria-label="Buscar en la biblioteca"
                        maxLength={120}
                        className="pl-9"
                    />
                </div>

                {error ? <div role="alert" className="space-y-2 text-sm text-danger">{error}<Button type="button" onClick={() => setRetry((value) => value + 1)}>Reintentar</Button></div> : loading ? <p role="status" className="py-6 text-sm text-muted">Cargando biblioteca…</p> : filtered.length === 0 ? (
                    <p className="rounded-control border border-dashed border-line px-4 py-10 text-center text-xs text-faint">
                        Sin contenido disponible.
                    </p>
                ) : (
                    <div className="grid max-h-[55vh] grid-cols-2 gap-3 overflow-y-auto pr-1 sm:grid-cols-4">
                        {filtered.map((asset) => {
                            const selected = selectedIds.includes(asset.id);
                            return (
                                <button
                                    key={asset.id}
                                    type="button"
                                    onClick={() => onSelect(asset)}
                                    className={cn(
                                        'relative min-w-0 space-y-1.5 rounded-control border p-1.5 text-left transition-colors',
                                        selected ? 'border-accent/60 bg-accent/10' : 'border-line hover:border-line-strong',
                                    )}
                                >
                                    <MediaThumbnail media={asset} />
                                    <p className="truncate text-[11px] text-fg">{asset.filename}</p>
                                    <p className="text-[10px] text-faint">
                                        {asset.type.label}
                                        {asset.formatted_duration ? ` · ${asset.formatted_duration}` : ''}
                                    </p>
                                    {selected ? (
                                        <span className="absolute right-2 top-2 flex size-4 items-center justify-center rounded-full bg-accent text-[#20170a]">
                                            <Check className="size-3" strokeWidth={3} />
                                        </span>
                                    ) : null}
                                </button>
                            );
                        })}
                    </div>
                )}
                {endpoint && result && result.last_page > 1 ? <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
                    <Button type="button" size="sm" disabled={loading || page === 1} onClick={() => setPage((value) => value - 1)}>Anterior</Button>
                    <span>Página {result.current_page} de {result.last_page}</span>
                    <Button type="button" size="sm" disabled={loading || page >= result.last_page} onClick={() => setPage((value) => value + 1)}>Siguiente</Button>
                </div> : null}
            </DialogContent>
        </Dialog>
    );
}
