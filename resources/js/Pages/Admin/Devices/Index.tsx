import { Head, Link, router } from '@inertiajs/react';
import { Eye, KeyRound, MonitorPlay, RefreshCw, Settings2, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ActionMenu } from '@/Components/app/ActionMenu';
import { ConfirmDialog } from '@/Components/app/ConfirmDialog';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { FilterBar } from '@/Components/app/FilterBar';
import { PageHeader } from '@/Components/app/PageHeader';
import { Pagination } from '@/Components/app/Pagination';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Progress } from '@/Components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { usePermissions } from '@/Hooks/usePermissions';
import type { DeviceEntity, Option, Paginated } from '@/Types';
import { formatBytes, formatRelative } from '@/Utils/format';

interface BusinessOption {
    id: number;
    name: string;
}

export default function DevicesIndex({
    devices,
    filters,
    options,
}: {
    devices: Paginated<DeviceEntity>;
    filters: { search?: string; status?: string; business_id?: string; city?: string; app_version?: string };
    options: { statuses: Option[]; businesses: BusinessOption[]; cities: string[]; appVersions: string[] };
}) {
    const { can } = usePermissions();
    const [deletingDevice, setDeletingDevice] = useState<DeviceEntity | null>(null);
    const applyFilter = (patch: Record<string, string>) => {
        const next: Record<string, string> = { ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key]) delete next[key];
        });
        router.get('/admin/devices', next, { preserveState: true, preserveScroll: true, replace: true });
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
                                row.status.value === 'online'
                                    ? 'bg-positive'
                                    : row.status.value === 'offline'
                                      ? 'bg-danger'
                                      : row.status.value === 'maintenance'
                                        ? 'bg-warning'
                                        : 'bg-faint'
                            }`}
                        />
                    </span>
                    <div className="min-w-0">
                        <Link href={`/admin/devices/${row.id}`} className="block truncate font-medium text-fg hover:text-accent">
                            {row.name}
                        </Link>
                        <span className="metric block truncate text-[11px] text-faint">{row.uuid.slice(0, 13)}…</span>
                    </div>
                </div>
            ),
        },
        { key: 'business', header: 'Negocio', cell: (row) => <span className="truncate text-muted">{row.business?.name ?? '—'}</span> },
        {
            key: 'location',
            header: 'Ubicación',
            cell: (row) => (
                <span className="truncate text-muted">
                    {row.location?.name ?? '—'}
                    {row.location?.city ? <span className="text-faint"> · {row.location.city}</span> : null}
                </span>
            ),
        },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'last_seen', header: 'Última señal', cell: (row) => <span className="text-muted">{formatRelative(row.last_seen_at)}</span> },
        { key: 'app', header: 'App', cell: (row) => <span className="metric text-muted">{row.app_version ?? '—'}</span> },
        {
            key: 'storage',
            header: 'Almacenamiento',
            cell: (row) =>
                row.storage_usage !== null ? (
                    <div className="w-28">
                        <div className="mb-1 flex justify-between text-[10px] text-faint">
                            <span>{formatBytes(row.storage_free)} libres</span>
                            <span className="metric">{row.storage_usage}%</span>
                        </div>
                        <Progress
                            value={row.storage_usage}
                            indicatorClassName={row.storage_usage > 85 ? 'bg-danger' : 'bg-accent'}
                        />
                    </div>
                ) : (
                    <span className="text-faint">—</span>
                ),
        },
        { key: 'manifest', header: 'Manifiesto', cell: (row) => <span className="metric text-muted">{row.current_manifest_version ?? '—'}</span> },
        { key: 'layout', header: 'Layout', cell: (row) => <span className="text-muted">{row.current_layout?.name ?? '—'}</span> },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (row) => (
                <div className="flex justify-end">
                    <ActionMenu
                        items={[
                            { label: 'Ver detalle', icon: Eye, onSelect: () => router.visit(`/admin/devices/${row.id}`) },
                            {
                                label: 'Sincronizar ahora',
                                icon: RefreshCw,
                                onSelect: () => router.post(`/admin/devices/${row.id}/sync`, {}, { preserveScroll: true }),
                            },
                            {
                                label: 'Revocar acceso',
                                icon: KeyRound,
                                variant: 'danger',
                                separatorBefore: true,
                                onSelect: () => {
                                    if (confirm(`¿Revocar el acceso de ${row.name}? Deberá activarse de nuevo.`)) {
                                        router.post(`/admin/devices/${row.id}/revoke-token`, {}, { preserveScroll: true });
                                    }
                                },
                            },
                            {
                                label: 'Eliminar pantalla',
                                icon: Trash2,
                                variant: 'danger',
                                hidden: !can('devices.manage'),
                                onSelect: () => setDeletingDevice(row),
                            },
                        ]}
                    />
                </div>
            ),
        },
    ];

    return (
        <AdminLayout>
            <Head title="Pantallas" />

            <PageHeader
                title="Pantallas"
                description="Dispositivos Android TV instalados en la red."
                actions={
                    <>
                        {can('devices.manage') ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/devices/global-pin"><KeyRound className="size-4" />PIN global</Link>
                            </Button>
                        ) : null}
                        <Button variant="secondary" size="sm" asChild>
                            <Link href="/admin/devices/activations">
                                <KeyRound className="size-4" />
                                Activaciones
                            </Link>
                        </Button>
                    </>
                }
            />

            <div className="mt-6 rounded-card border border-line bg-card p-4">
                <FilterBar search={filters.search} onSearch={(value) => applyFilter({ search: value })} searchPlaceholder="Buscar por nombre, UUID o código…">
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
                    <Select value={filters.business_id ?? 'all'} onValueChange={(value) => applyFilter({ business_id: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Negocio" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos los negocios</SelectItem>
                            {options.businesses.map((business) => (
                                <SelectItem key={business.id} value={String(business.id)}>
                                    {business.name}
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
                    <Select value={filters.app_version ?? 'all'} onValueChange={(value) => applyFilter({ app_version: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-36">
                            <SelectValue placeholder="Versión" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todas las versiones</SelectItem>
                            {options.appVersions.map((version) => (
                                <SelectItem key={version} value={version}>
                                    {version}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button variant="ghost" size="sm" onClick={() => router.get('/admin/devices', {}, { replace: true })}>
                        <Settings2 className="size-3.5" />
                        Limpiar
                    </Button>
                </FilterBar>

                <div className="mt-4">
                    <DataTable
                        columns={columns}
                        rows={devices.data}
                        keyExtractor={(row) => row.id}
                        onRowClick={(row) => router.visit(`/admin/devices/${row.id}`)}
                        empty={<EmptyState icon={MonitorPlay} title="Sin pantallas" description="Ajusta los filtros o activa una nueva pantalla." />}
                    />
                </div>
                <Pagination paginator={devices} />
            </div>
            <ConfirmDialog
                open={deletingDevice !== null}
                onOpenChange={(open) => { if (!open) setDeletingDevice(null); }}
                title="Eliminar pantalla"
                description={`¿Eliminar «${deletingDevice?.name ?? ''}»? Se borrarán sus reportes, comandos y manifiestos. Perderá su acceso y necesitará una nueva activación.`}
                confirmLabel="Eliminar pantalla"
                onConfirm={() => {
                    if (deletingDevice) router.delete(`/admin/devices/${deletingDevice.id}`, { preserveScroll: true });
                }}
            />
        </AdminLayout>
    );
}
