import { Head, Link, router } from '@inertiajs/react';
import { Archive, Eye, Megaphone, Pause, Pencil, Play, Plus, Trash2 } from 'lucide-react';
import { ActionMenu } from '@/Components/app/ActionMenu';
import { ConfirmDialog } from '@/Components/app/ConfirmDialog';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { FilterBar } from '@/Components/app/FilterBar';
import { PageHeader } from '@/Components/app/PageHeader';
import { Pagination } from '@/Components/app/Pagination';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { usePermissions } from '@/Hooks/usePermissions';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { CampaignEntity, Option, Paginated } from '@/Types';
import { formatNumber } from '@/Utils/format';
import { useState } from 'react';
import { toast } from 'sonner';

export default function CampaignsIndex({
    campaigns,
    filters,
    options,
}: {
    campaigns: Paginated<CampaignEntity>;
    filters: { search?: string; status?: string; advertiser_id?: string; city?: string; category?: string };
    options: { statuses: Option[]; advertisers: Array<{ id: number; name: string }>; cities: string[]; categories: Option[] };
}) {
    const { can } = usePermissions();
    const [deleting, setDeleting] = useState<CampaignEntity | null>(null);

    const applyFilter = (patch: Record<string, string>) => {
        const next: Record<string, string> = { ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key]) delete next[key];
        });
        router.get('/admin/campaigns', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const transition = (campaign: CampaignEntity, action: 'publish' | 'pause' | 'resume' | 'archive') => {
        router.post(`/admin/campaigns/${campaign.id}/${action}`, {}, { preserveScroll: true });
    };

    const columns: Array<Column<CampaignEntity>> = [
        {
            key: 'name',
            header: 'Campaña',
            cell: (row) => (
                <div className="min-w-0">
                    <Link href={`/admin/campaigns/${row.id}`} className="block truncate font-medium text-fg hover:text-accent">
                        {row.name}
                    </Link>
                    <span className="block truncate text-xs text-faint">{row.advertiser?.name ?? 'Sin anunciante'}</span>
                </div>
            ),
        },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'schedule', header: 'Programación', cell: (row) => <span className="text-xs text-muted">{row.schedule_label}</span> },
        { key: 'screens', header: 'Pantallas', cell: (row) => <span className="metric text-fg">{row.target_screen_count}</span> },
        { key: 'playbacks', header: 'Reproducciones', cell: (row) => <span className="metric text-fg">{formatNumber(row.playbacks_count)}</span> },
        {
            key: 'progress',
            header: 'Progreso',
            cell: (row) =>
                row.progress !== null ? (
                    <div className="w-28">
                        <div className="h-1.5 overflow-hidden rounded-full bg-elevated">
                            <div className="h-full rounded-full bg-accent" style={{ width: `${row.progress}%` }} />
                        </div>
                        <span className="metric mt-1 block text-[10px] text-faint">{row.progress}% de la meta</span>
                    </div>
                ) : (
                    <span className="text-faint">—</span>
                ),
        },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (row) => (
                <div className="flex justify-end">
                    <ActionMenu
                        items={[
                            { label: 'Ver detalle', icon: Eye, onSelect: () => router.visit(`/admin/campaigns/${row.id}`) },
                            { label: 'Editar', icon: Pencil, onSelect: () => router.visit(`/admin/campaigns/${row.id}/edit`), hidden: !can('campaigns.edit') },
                            {
                                label: row.status.value === 'active' ? 'Pausar' : 'Publicar',
                                icon: row.status.value === 'active' ? Pause : Play,
                                hidden: !can('campaigns.publish') || ['completed', 'archived'].includes(row.status.value),
                                onSelect: () => transition(row, row.status.value === 'active' ? 'pause' : row.status.value === 'paused' ? 'resume' : 'publish'),
                            },
                            {
                                label: 'Archivar',
                                icon: Archive,
                                hidden: !can('campaigns.publish'),
                                onSelect: () => transition(row, 'archive'),
                            },
                            {
                                label: 'Eliminar',
                                icon: Trash2,
                                variant: 'danger',
                                separatorBefore: true,
                                hidden: !can('campaigns.delete'),
                                onSelect: () => setDeleting(row),
                            },
                        ]}
                    />
                </div>
            ),
        },
    ];

    return (
        <AdminLayout>
            <Head title="Campañas" />

            <PageHeader
                title="Campañas"
                description="Planificación y entrega de publicidad en la red."
                actions={
                    can('campaigns.create') ? (
                        <Button variant="primary" size="sm" asChild>
                            <Link href="/admin/campaigns/create">
                                <Plus className="size-4" />
                                Nueva campaña
                            </Link>
                        </Button>
                    ) : null
                }
            />

            <div className="mt-6 rounded-card border border-line bg-card p-4">
                <FilterBar search={filters.search} onSearch={(value) => applyFilter({ search: value })} searchPlaceholder="Buscar campaña…">
                    <Select value={filters.status ?? 'all'} onValueChange={(value) => applyFilter({ status: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-40">
                            <SelectValue placeholder="Estado" />
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
                    <Select value={filters.advertiser_id ?? 'all'} onValueChange={(value) => applyFilter({ advertiser_id: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Anunciante" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos los anunciantes</SelectItem>
                            {options.advertisers.map((advertiser) => (
                                <SelectItem key={advertiser.id} value={String(advertiser.id)}>
                                    {advertiser.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.city ?? 'all'} onValueChange={(value) => applyFilter({ city: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-40">
                            <SelectValue placeholder="Ciudad" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todas las ciudades</SelectItem>
                            {options.cities.map((city) => (
                                <SelectItem key={city} value={city}>
                                    {city}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.category ?? 'all'} onValueChange={(value) => applyFilter({ category: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-44">
                            <SelectValue placeholder="Categoría" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todas las categorías</SelectItem>
                            {options.categories.map((category) => (
                                <SelectItem key={category.value} value={category.value}>
                                    {category.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FilterBar>

                <div className="mt-4">
                    <DataTable
                        columns={columns}
                        rows={campaigns.data}
                        keyExtractor={(row) => row.id}
                        empty={<EmptyState icon={Megaphone} title="Sin campañas" description="Crea la primera campaña publicitaria." />}
                    />
                </div>
                <Pagination paginator={campaigns} />
            </div>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(value) => (!value ? setDeleting(null) : null)}
                title={`Eliminar ${deleting?.name ?? ''}`}
                description="Se eliminarán las creatividades y métricas asociadas. Esta acción no se puede deshacer."
                confirmLabel="Eliminar campaña"
                onConfirm={() => {
                    if (!deleting) return;
                    router.delete(`/admin/campaigns/${deleting.id}`, { onSuccess: () => toast.success('Campaña eliminada.') });
                }}
            />
        </AdminLayout>
    );
}
