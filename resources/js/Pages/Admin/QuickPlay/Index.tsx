import { Head, Link, router } from '@inertiajs/react';
import { Eye, Image as ImageIcon, Play, Zap } from 'lucide-react';
import { ActionMenu } from '@/Components/app/ActionMenu';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { FilterBar } from '@/Components/app/FilterBar';
import { MediaThumbnail } from '@/Components/app/MediaThumbnail';
import { PageHeader } from '@/Components/app/PageHeader';
import { Pagination } from '@/Components/app/Pagination';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { QuickPlayActions } from '@/Components/app/QuickPlayActions';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { usePermissions } from '@/Hooks/usePermissions';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { BusinessLayout } from '@/Layouts/BusinessLayout';
import type { Option, Paginated, QuickPlayEntity } from '@/Types';
import { formatNumber, formatRelative } from '@/Utils/format';

export default function QuickPlayIndex({
    quickPlays,
    filters,
    statuses,
    portal = 'admin',
}: {
    portal?: 'admin' | 'business';
    quickPlays: Paginated<QuickPlayEntity>;
    filters: { search?: string; status?: string };
    statuses: Option[];
}) {
    const { can } = usePermissions();
    const PageLayout = portal === 'business' ? BusinessLayout : AdminLayout;
    const basePath = `/${portal}/quick-play`;


    const applyFilter = (patch: Record<string, string>) => {
        const next: Record<string, string> = { ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key]) delete next[key];
        });
        router.get(basePath, next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const columns: Array<Column<QuickPlayEntity>> = [
        {
            key: 'media',
            header: 'Contenido',
            cell: (row) => (
                <div className="flex items-center gap-3">
                    <MediaThumbnail media={row.media} className="w-16" />
                    <div className="min-w-0">
                        <Link href={`${basePath}/${row.id}`} className="block truncate font-medium text-fg hover:text-accent">
                            {row.media?.filename ?? 'Contenido eliminado'}
                        </Link>
                        <span className="block truncate text-xs text-faint">
                            {row.media?.type?.label ?? '—'} · {row.duration_label}
                        </span>
                    </div>
                </div>
            ),
        },
        { key: 'mode', header: 'Modo', cell: (row) => <span className="text-muted">{row.display_mode.label}</span> },
        { key: 'scope', header: 'Destino', cell: (row) => <span className="text-muted">{row.scope.label}</span> },
        {
            key: 'targets',
            header: 'Pantallas',
            cell: (row) => <span className="metric text-fg">{formatNumber(row.targets_count)}</span>,
        },
        {
            key: 'delivery',
            header: 'Entrega',
            cell: (row) => (
                <div className="flex items-center gap-3 text-xs">
                    <span className="inline-flex items-center gap-1 text-positive">
                        <Play className="size-3" /> {row.delivered_count}
                    </span>
                    {row.failed_count > 0 ? (
                        <span className="text-danger">{row.failed_count} fallidas</span>
                    ) : null}
                    {row.pending_count > 0 ? (
                        <span className="text-info">{row.pending_count} en curso</span>
                    ) : null}
                </div>
            ),
        },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'user', header: 'Enviado por', cell: (row) => <span className="text-muted">{row.user}</span> },
        { key: 'created', header: 'Enviado', cell: (row) => <span className="text-muted">{formatRelative(row.created_at)}</span> },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (row) => (
                <div className="flex justify-end gap-2" onClick={(event) => event.stopPropagation()}>
                    <QuickPlayActions quickPlay={row} portal={portal} />
                    <ActionMenu items={[{ label: 'Ver entrega', icon: Eye, onSelect: () => router.visit(`${basePath}/${row.id}`) }]} />
                </div>
            ),
        },
    ];

    return (
        <PageLayout>
            <Head title="Reproducción inmediata" />

            <PageHeader
                eyebrow="Operación"
                title="Reproducción inmediata"
                description="Envía una imagen o video al instante a una o varias pantallas, sin crear una campaña."
                actions={
                    can(portal === 'business' ? 'business.playlists.manage' : 'quick_play.send') ? (
                        <Button variant="primary" size="sm" asChild>
                            <Link href={`${basePath}/create`}>
                                <Zap className="size-4" />
                                Nueva reproducción
                            </Link>
                        </Button>
                    ) : null
                }
            />

            <div className="mt-4 rounded-card border border-line bg-surface p-4 text-xs text-muted">
                <p className="flex items-start gap-2">
                    <Zap className="size-3.5 shrink-0 text-accent" />
                    Al terminar, cada pantalla vuelve automáticamente a su playlist y layout programados. En modo
                    pantalla completa se restaura el layout anterior.
                </p>
            </div>

            <div className="mt-4 rounded-card border border-line bg-card p-4">
                <FilterBar search={filters.search} onSearch={(value) => applyFilter({ search: value })} searchPlaceholder="Buscar por archivo…">
                    <Select value={filters.status ?? 'all'} onValueChange={(value) => applyFilter({ status: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-44">
                            <SelectValue placeholder="Estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos los estados</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem key={status.value} value={status.value}>
                                    {status.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FilterBar>

                <div className="mt-4">
                    <DataTable
                        columns={columns}
                        rows={quickPlays.data}
                        keyExtractor={(row) => row.id}
                        onRowClick={(row) => router.visit(`${basePath}/${row.id}`)}
                        empty={
                            <EmptyState
                                icon={ImageIcon}
                                title="Sin reproducciones inmediatas"
                                description="Envía contenido puntual a las pantallas sin crear una campaña."
                            />
                        }
                    />
                </div>
                <Pagination paginator={quickPlays} />
            </div>
        </PageLayout>
    );
}
