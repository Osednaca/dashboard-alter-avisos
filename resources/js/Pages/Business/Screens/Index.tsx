import { Head, router } from '@inertiajs/react';
import { Eye, MonitorPlay, RefreshCw, Sparkles } from 'lucide-react';
import { ActionMenu } from '@/Components/app/ActionMenu';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { FilterBar } from '@/Components/app/FilterBar';
import { PageHeader } from '@/Components/app/PageHeader';
import { StatCard } from '@/Components/app/StatCard';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Progress } from '@/Components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { usePermissions } from '@/Hooks/usePermissions';
import { BusinessLayout } from '@/Layouts/BusinessLayout';
import type { DeviceEntity } from '@/Types';
import { formatBytes, formatRelative } from '@/Utils/format';

export default function ScreensIndex({
    devices,
    filters,
    locations,
    summary,
}: {
    devices: DeviceEntity[];
    filters: { search?: string; status?: string; location_id?: string };
    locations: Array<{ id: number; name: string; city: string }>;
    summary: { total: number; online: number };
}) {
    const { can } = usePermissions();

    const applyFilter = (patch: Record<string, string>) => {
        const next: Record<string, string> = { ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key]) delete next[key];
        });
        router.get('/business/screens', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const columns: Array<Column<DeviceEntity>> = [
        {
            key: 'name',
            header: 'Pantalla',
            cell: (row) => (
                <div className="flex items-center gap-3">
                    <span className="relative flex size-9 items-center justify-center rounded-control border border-line bg-surface text-accent">
                        <MonitorPlay className="size-4" />
                        <span
                            className={`absolute -right-0.5 -top-0.5 size-2.5 rounded-full border-2 border-card ${
                                row.is_online ? 'bg-positive' : 'bg-danger'
                            }`}
                        />
                    </span>
                    <div className="min-w-0">
                        <p className="truncate font-medium text-fg">{row.name}</p>
                        <p className="truncate text-xs text-faint">{row.location?.name ?? 'Sin ubicación'}</p>
                    </div>
                </div>
            ),
        },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'playlist', header: 'Playlist', cell: (row) => <span className="text-muted">{row.current_playlist?.name ?? '—'}</span> },
        {
            key: 'layout',
            header: 'Layout',
            cell: (row) => (
                <span className="text-muted">
                    {row.current_layout?.name ?? '—'}
                    {row.current_layout ? <span className="metric text-faint"> {row.current_layout.ratio}</span> : null}
                </span>
            ),
        },
        { key: 'sync', header: 'Última sinc.', cell: (row) => <span className="text-muted">{formatRelative(row.last_sync_at)}</span> },
        { key: 'app', header: 'App', cell: (row) => <span className="metric text-muted">{row.app_version ?? '—'}</span> },
        {
            key: 'storage',
            header: 'Almacenamiento',
            cell: (row) =>
                row.storage_usage !== null ? (
                    <div className="w-24">
                        <Progress value={row.storage_usage} indicatorClassName={row.storage_usage > 85 ? 'bg-danger' : 'bg-accent'} />
                        <span className="metric mt-1 block text-[10px] text-faint">{formatBytes(row.storage_free)} libres</span>
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
                            { label: 'Ver pantalla', icon: Eye, onSelect: () => router.visit(`/business/screens/${row.id}`) },
                            { label: 'Vista previa', icon: Sparkles, onSelect: () => router.visit(`/business/preview?device=${row.id}`) },
                            {
                                label: 'Sincronizar ahora',
                                icon: RefreshCw,
                                hidden: !can('business.devices.sync'),
                                onSelect: () => router.post(`/business/screens/${row.id}/sync`, {}, { preserveScroll: true }),
                            },
                        ]}
                    />
                </div>
            ),
        },
    ];

    return (
        <BusinessLayout>
            <Head title="Pantallas" />

            <PageHeader
                eyebrow="Pantallas"
                title="Tus pantallas"
                description={`${summary.online} de ${summary.total} en línea.`}
                actions={
                    <Button variant="secondary" size="sm" asChild>
                        <a href="/business/preview">
                            <Sparkles className="size-4" />
                            Vista previa
                        </a>
                    </Button>
                }
            />

            <div className="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
                <StatCard label="Pantallas" value={summary.total} icon={MonitorPlay} />
                <StatCard label="En línea" value={summary.online} hint="reportando" icon={MonitorPlay} delay={80} />
            </div>

            <div className="mt-4 rounded-card border border-line bg-card p-4">
                <FilterBar search={filters.search} onSearch={(value) => applyFilter({ search: value })} searchPlaceholder="Buscar pantalla…">
                    <Select value={filters.location_id ?? 'all'} onValueChange={(value) => applyFilter({ location_id: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-44">
                            <SelectValue placeholder="Ubicación" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todas las ubicaciones</SelectItem>
                            {locations.map((location) => (
                                <SelectItem key={location.id} value={String(location.id)}>
                                    {location.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.status ?? 'all'} onValueChange={(value) => applyFilter({ status: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-40">
                            <SelectValue placeholder="Estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos los estados</SelectItem>
                            <SelectItem value="online">En línea</SelectItem>
                            <SelectItem value="offline">Desconectada</SelectItem>
                            <SelectItem value="maintenance">Mantenimiento</SelectItem>
                        </SelectContent>
                    </Select>
                </FilterBar>

                <div className="mt-4">
                    <DataTable
                        columns={columns}
                        rows={devices}
                        keyExtractor={(row) => row.id}
                        onRowClick={(row) => router.visit(`/business/screens/${row.id}`)}
                        empty={<EmptyState icon={MonitorPlay} title="Sin pantallas" />}
                    />
                </div>
            </div>
        </BusinessLayout>
    );
}
