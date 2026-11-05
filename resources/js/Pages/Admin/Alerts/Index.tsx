import { Head, router } from '@inertiajs/react';
import { Bell, Check, CheckCheck, TriangleAlert } from 'lucide-react';
import { ActionMenu } from '@/Components/app/ActionMenu';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { FilterBar } from '@/Components/app/FilterBar';
import { PageHeader } from '@/Components/app/PageHeader';
import { Pagination } from '@/Components/app/Pagination';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { AlertEntity, Option, Paginated } from '@/Types';
import { formatRelative } from '@/Utils/format';

export default function AlertsIndex({
    alerts,
    filters,
    options,
}: {
    alerts: Paginated<AlertEntity>;
    filters: { status?: string; severity?: string; type?: string };
    options: { statuses: Option[]; severities: Option[]; types: Option[] };
}) {
    const applyFilter = (patch: Record<string, string>) => {
        const next: Record<string, string> = { ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key]) delete next[key];
        });
        router.get('/admin/alerts', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const columns: Array<Column<AlertEntity>> = [
        {
            key: 'title',
            header: 'Alerta',
            cell: (row) => (
                <div className="flex items-start gap-3">
                    <span
                        className={`mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full ${
                            row.severity.value === 'critical'
                                ? 'bg-danger/12 text-danger'
                                : row.severity.value === 'warning'
                                  ? 'bg-warning/12 text-warning'
                                  : 'bg-info/12 text-info'
                        }`}
                    >
                        <TriangleAlert className="size-3.5" />
                    </span>
                    <div className="min-w-0">
                        <p className="truncate font-medium text-fg">{row.title}</p>
                        <p className="truncate text-xs text-muted">{row.message ?? '—'}</p>
                    </div>
                </div>
            ),
        },
        { key: 'type', header: 'Tipo', cell: (row) => <span className="text-muted">{row.type.label}</span> },
        { key: 'severity', header: 'Severidad', cell: (row) => <StatusBadge value={row.severity} /> },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'triggered', header: 'Disparada', cell: (row) => <span className="text-muted">{formatRelative(row.triggered_at)}</span> },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (row) => (
                <div className="flex justify-end">
                    <ActionMenu
                        items={[
                            {
                                label: 'Reconocer',
                                icon: Check,
                                hidden: row.status.value !== 'open',
                                onSelect: () => router.post(`/admin/alerts/${row.id}/acknowledge`, {}, { preserveScroll: true }),
                            },
                            {
                                label: 'Resolver',
                                icon: CheckCheck,
                                hidden: row.status.value === 'resolved',
                                onSelect: () => router.post(`/admin/alerts/${row.id}/resolve`, {}, { preserveScroll: true }),
                            },
                        ]}
                    />
                </div>
            ),
        },
    ];

    return (
        <AdminLayout>
            <Head title="Alertas" />

            <PageHeader title="Alertas" description="Incidentes operativos de la red de pantallas." />

            <div className="mt-6 rounded-card border border-line bg-card p-4">
                <FilterBar>
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
                    <Select value={filters.severity ?? 'all'} onValueChange={(value) => applyFilter({ severity: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-40">
                            <SelectValue placeholder="Severidad" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todas las severidades</SelectItem>
                            {options.severities.map((severity) => (
                                <SelectItem key={severity.value} value={severity.value}>
                                    {severity.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.type ?? 'all'} onValueChange={(value) => applyFilter({ type: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-56">
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
                </FilterBar>

                <div className="mt-4">
                    <DataTable
                        columns={columns}
                        rows={alerts.data}
                        keyExtractor={(row) => row.id}
                        empty={<EmptyState icon={Bell} title="Sin alertas" description="La red está operando sin incidentes." />}
                    />
                </div>
                <Pagination paginator={alerts} />
            </div>
        </AdminLayout>
    );
}
