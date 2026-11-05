import { Head, Link, router, useForm } from '@inertiajs/react';
import { Copy, ListVideo, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/Components/app/ConfirmDialog';
import { EmptyState } from '@/Components/app/EmptyState';
import { FilterBar } from '@/Components/app/FilterBar';
import { MediaThumbnail } from '@/Components/app/MediaThumbnail';
import { PageHeader } from '@/Components/app/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { usePermissions } from '@/Hooks/usePermissions';
import { BusinessLayout } from '@/Layouts/BusinessLayout';
import type { PlaylistSummary } from '@/Types';
import { formatDuration } from '@/Utils/format';

export default function PlaylistsIndex({
    playlists,
    filters,
}: {
    playlists: PlaylistSummary[];
    filters: { search?: string };
}) {
    const { can } = usePermissions();
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<PlaylistSummary | null>(null);

    const form = useForm({ name: '' });

    const search = (value: string) => {
        router.get('/business/playlists', value ? { search: value } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <BusinessLayout>
            <Head title="Playlists" />

            <PageHeader
                eyebrow="Contenido"
                title="Playlists"
                description="Organiza el contenido de tus pantallas en listas de reproducción."
                actions={
                    can('business.playlists.manage') ? (
                        <Button variant="primary" size="sm" onClick={() => (form.reset(), form.clearErrors(), setCreating(true))}>
                            <Plus className="size-4" />
                            Nueva playlist
                        </Button>
                    ) : null
                }
            />

            <div className="mt-6">
                <FilterBar search={filters.search} onSearch={search} searchPlaceholder="Buscar playlist…" />
            </div>

            <div className="mt-4">
                {playlists.length === 0 ? (
                    <EmptyState
                        icon={ListVideo}
                        title="Sin playlists"
                        description="Crea una lista y agrega contenido de tu biblioteca."
                    />
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {playlists.map((playlist) => (
                            <Card key={playlist.id} className="group">
                                <CardContent className="space-y-3 pt-4">
                                    <Link href={`/business/playlists/${playlist.id}`} className="block">
                                        <MediaThumbnail media={playlist.cover} className="w-full" />
                                    </Link>
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <Link
                                                href={`/business/playlists/${playlist.id}`}
                                                className="block truncate font-medium text-fg hover:text-accent"
                                            >
                                                {playlist.name}
                                            </Link>
                                            <p className="text-[11px] text-faint">
                                                {playlist.items_count} elementos · {formatDuration(playlist.total_duration)}
                                            </p>
                                        </div>
                                        <Badge tone={playlist.status.value === 'active' ? 'positive' : 'neutral'}>
                                            {playlist.status.label}
                                        </Badge>
                                    </div>
                                    {can('business.playlists.manage') ? (
                                        <div className="flex items-center gap-2 border-t border-line pt-3">
                                            <Button variant="secondary" size="sm" asChild className="flex-1">
                                                <Link href={`/business/playlists/${playlist.id}`}>Abrir</Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label="Duplicar"
                                                onClick={() => router.post(`/business/playlists/${playlist.id}/duplicate`, {}, { preserveScroll: true })}
                                            >
                                                <Copy className="size-3.5" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label="Eliminar"
                                                onClick={() => setDeleting(playlist)}
                                            >
                                                <Trash2 className="size-3.5 text-danger" />
                                            </Button>
                                        </div>
                                    ) : null}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Nueva playlist</DialogTitle>
                    </DialogHeader>
                    <Input
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        placeholder="Ej: Menú Principal"
                        autoFocus
                    />
                    {form.errors.name ? <p className="text-xs text-danger">{form.errors.name}</p> : null}
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setCreating(false)}>
                            Cancelar
                        </Button>
                        <Button variant="primary" disabled={form.processing} onClick={() => form.post('/business/playlists')}>
                            Crear playlist
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(open) => (!open ? setDeleting(null) : null)}
                title={`Eliminar ${deleting?.name ?? ''}`}
                description="Se eliminará la lista y sus programaciones asociadas. Esta acción no se puede deshacer."
                confirmLabel="Eliminar playlist"
                onConfirm={() => {
                    if (!deleting) return;
                    router.delete(`/business/playlists/${deleting.id}`, { preserveScroll: true });
                }}
            />
        </BusinessLayout>
    );
}
