import { UploadDropzone } from '@/Components/app/UploadDropzone';
import { LiveStreamDialog } from '@/Components/app/LiveStreamDialog';
import { usePermissions } from '@/Hooks/usePermissions';
import { Head, router, usePage } from '@inertiajs/react';
import { Grid2X2, Image as ImageIcon, List, Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { FilterBar } from '@/Components/app/FilterBar';
import { LibrarySelectionBar, LibrarySelectionCheckbox } from '@/Components/app/LibrarySelection';
import { toggleLibrarySelection } from '@/Utils/library-selection';
import { MediaActionsMenu } from '@/Components/app/MediaActionsMenu';
import { MediaThumbnail } from '@/Components/app/MediaThumbnail';
import { PageHeader } from '@/Components/app/PageHeader';
import { Pagination } from '@/Components/app/Pagination';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { MediaEntity, Option, Paginated } from '@/Types';
import { formatDate } from '@/Utils/format';
import { cn } from '@/Utils/cn';

export default function CreativesIndex({
    assets,
    filters,
    options,
}: {
    assets: Paginated<MediaEntity>;
    filters: { search?: string; type?: string; processing_status?: string };
    options: { types: Option[]; statuses: Option[]; advertisers: Array<{ id: number; name: string }> };
}) {
    const [view, setView] = useState<'grid' | 'list'>('grid');
    const { errors } = usePage().props;
    const selectionError = Object.entries(errors).find(([key]) => key === 'media_ids' || key.startsWith('media_ids.'))?.[1];
    const { can } = usePermissions();
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const canSchedule = can('campaigns.create');
    const toggleSelection = (id: number) => setSelectedIds((ids) => toggleLibrarySelection(ids, id, 30));
    const navigatePage = (url: string) => router.visit(url, { preserveState: true, preserveScroll: true });
    const [uploading, setUploading] = useState(false);
    const [previewMedia, setPreviewMedia] = useState<MediaEntity | null>(null);
    const [deleting, setDeleting] = useState<MediaEntity | null>(null);

    const [advertiserId, setAdvertiserId] = useState('');
    const [uploadBusy, setUploadBusy] = useState(false);

    const applyFilter = (patch: Record<string, string>) => {
        const next: Record<string, string> = { ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key]) delete next[key];
        });
        router.get('/admin/creatives', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const assetActions = (asset: MediaEntity) => (
        <MediaActionsMenu
            filename={asset.filename}
            onPreview={asset.type.value !== 'live_stream' ? () => setPreviewMedia(asset) : undefined}
            onDelete={can('creatives.manage') ? () => setDeleting(asset) : undefined}
        />
    );

    const selectionCheckbox = (asset: MediaEntity) => <LibrarySelectionCheckbox media={asset} selected={selectedIds.includes(asset.id)} full={selectedIds.length >= 30} onToggle={() => toggleSelection(asset.id)} />;

    const columns: Array<Column<MediaEntity>> = [
        ...(canSchedule ? [{ key: 'select', header: 'Elegir', cell: selectionCheckbox }] : []),
        {
            key: 'file',
            header: 'Archivo',
            cell: (row) => (
                <div className="flex items-center gap-3">
                    <MediaThumbnail media={row} className="w-16" />
                    <div className="min-w-0">
                        <p className="truncate font-medium text-fg">{row.filename}</p>
                        <p className="text-xs text-faint">{row.mime_type}</p>
                    </div>
                </div>
            ),
        },
        { key: 'type', header: 'Tipo', cell: (row) => <span className="text-muted">{row.type.label}</span> },
        { key: 'resolution', header: 'Resolución', cell: (row) => <span className="metric text-muted">{row.resolution ?? '—'}</span> },
        { key: 'duration', header: 'Duración', cell: (row) => <span className="metric text-muted">{row.formatted_duration ?? '—'}</span> },
        { key: 'size', header: 'Tamaño', cell: (row) => <span className="metric text-muted">{row.human_filesize}</span> },
        { key: 'status', header: 'Proceso', cell: (row) => <StatusBadge value={row.processing_status} /> },
        { key: 'usage', header: 'Usos', cell: (row) => <span className="metric text-fg">{row.usage_count}</span> },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (row) => assetActions(row),
        },
    ];

    return (
        <AdminLayout>
            <Head title="Creatividades" />

            <PageHeader
                title="Creatividades"
                description="Biblioteca de imágenes y videos para las campañas."
                actions={
                    <>
                        {can('creatives.manage') ? <LiveStreamDialog onCreated={() => router.reload({only: ['assets']})} /> : null}
                        <div className="flex overflow-hidden rounded-control border border-line">
                            <button
                                type="button"
                                onClick={() => setView('grid')}
                                className={cn('p-2', view === 'grid' ? 'bg-elevated text-fg' : 'text-faint')}
                                aria-label="Vista de cuadrícula"
                            >
                                <Grid2X2 className="size-4" />
                            </button>
                            <button
                                type="button"
                                onClick={() => setView('list')}
                                className={cn('p-2', view === 'list' ? 'bg-elevated text-fg' : 'text-faint')}
                                aria-label="Vista de lista"
                            >
                                <List className="size-4" />
                            </button>
                        </div>
                        <Button variant="primary" size="sm" onClick={() => setUploading(true)}>
                            <Plus className="size-4" />
                            Subir creatividad
                        </Button>
                    </>
                }
            />

            <div className="mt-6 rounded-card border border-line bg-card p-4">
                <FilterBar search={filters.search} onSearch={(value) => applyFilter({ search: value })} searchPlaceholder="Buscar por nombre de archivo…">
                    <Select value={filters.type ?? 'all'} onValueChange={(value) => applyFilter({ type: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-36">
                            <SelectValue placeholder="Tipo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos los tipos</SelectItem>
                            {options.types.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.processing_status ?? 'all'} onValueChange={(value) => applyFilter({ processing_status: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-40">
                            <SelectValue placeholder="Proceso" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos los estados</SelectItem>
                            {options.statuses.map((status) => (
                                <SelectItem key={status.value} value={status.value}>
                                    {status.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FilterBar>

                {canSchedule ? <LibrarySelectionBar count={selectedIds.length} limit={30} label="Crear campaña" onClear={() => setSelectedIds([])} onCreate={() => router.get('/admin/campaigns/create', { media_ids: selectedIds })} /> : null}

                {selectionError ? <p className="mt-2 text-xs text-danger" role="alert">{selectionError}</p> : null}

                <div className="mt-4">
                    {assets.data.length === 0 ? (
                        <EmptyState icon={ImageIcon} title="Sin creatividades" description="Sube imágenes JPG, PNG, WebP o videos MP4." />
                    ) : view === 'grid' ? (
                        <div className="grid grid-cols-1 gap-4 min-[400px]:grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                            {assets.data.map((asset) => (
                                <Card key={asset.id} className="group">
                                    <CardContent className="space-y-2 pt-4">
                                        <div className="relative">
                                            <MediaThumbnail media={asset} />
                                            {canSchedule ? <div className="absolute left-2 top-2">{selectionCheckbox(asset)}</div> : null}
                                        </div>
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate text-xs text-fg">{asset.filename}</p>
                                                <p className="text-[11px] text-faint">
                                                    {asset.resolution ?? '—'} · {asset.human_filesize}
                                                </p>
                                            </div>
                                            {assetActions(asset)}
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <StatusBadge value={asset.processing_status} />
                                            <span className="text-[11px] text-faint">{asset.usage_count} usos</span>
                                        </div>
                                        <p className="text-[10px] text-faint">{formatDate(asset.created_at)}</p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        <DataTable columns={columns} rows={assets.data} keyExtractor={(row) => row.id} empty={<EmptyState icon={ImageIcon} title="Sin creatividades" />} />
                    )}
                </div>
                <Pagination paginator={assets} onNavigate={navigatePage} />
            </div>

            <Dialog open={!!previewMedia} onOpenChange={(open) => { if (!open) setPreviewMedia(null); }}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader><DialogTitle>{previewMedia?.filename}</DialogTitle></DialogHeader>
                    {previewMedia?.type.value === 'video' ? (
                        <video src={previewMedia.url} controls autoPlay className="max-h-[70vh] w-full" />
                    ) : previewMedia ? (
                        <img src={previewMedia.url} alt={previewMedia.filename} className="max-h-[70vh] w-full object-contain" />
                    ) : null}
                </DialogContent>
            </Dialog>

            <Dialog open={uploading} onOpenChange={(open) => { if (!uploadBusy) setUploading(open); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Subir creatividad</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <Label>Anunciante (opcional)</Label>
                        <Select disabled={uploadBusy} value={advertiserId || 'none'} onValueChange={(value) => setAdvertiserId(value === 'none' ? '' : value)}>
                            <SelectTrigger><SelectValue placeholder="Sin anunciante" /></SelectTrigger>
                            <SelectContent><SelectItem value="none">Sin anunciante</SelectItem>{options.advertisers.map((advertiser) => <SelectItem key={advertiser.id} value={String(advertiser.id)}>{advertiser.name}</SelectItem>)}</SelectContent>
                        </Select>
                        <UploadDropzone action="/admin/creatives" metadata={{ advertiser_id: advertiserId }} onBusyChange={setUploadBusy} />
                    </div>
                    <DialogFooter><Button variant="ghost" disabled={uploadBusy} onClick={() => setUploading(false)}>Cerrar</Button></DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={!!deleting} onOpenChange={(open) => (!open ? setDeleting(null) : null)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Eliminar creatividad</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted">
                        ¿Eliminar <span className="text-fg">{deleting?.filename}</span>? No se puede eliminar una
                        creatividad usada por una campaña activa.
                    </p>
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setDeleting(null)}>
                            Cancelar
                        </Button>
                        <Button
                            variant="danger"
                            onClick={() => {
                                if (!deleting) return;
                                router.delete(`/admin/creatives/${deleting.id}`, {
                                    preserveScroll: true,
                                    onSuccess: () => setDeleting(null),
                                });
                            }}
                        >
                            Eliminar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
