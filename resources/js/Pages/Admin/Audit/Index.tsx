import { Head, router } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { FilterBar } from '@/Components/app/FilterBar';
import { PageHeader } from '@/Components/app/PageHeader';
import { Pagination } from '@/Components/app/Pagination';
import { Badge } from '@/Components/ui/badge';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { AuditEntity, Paginated } from '@/Types';
import { formatDateTime } from '@/Utils/format';

export default function AuditIndex({
    logs,
    filters,
}: {
    logs: Paginated<AuditEntity>;
    filters: { search?: string; action?: string; entity_type?: string };
}) {
    const applyFilter = (patch: Record<string, string>) => {
        const next: Record<string, string> = { ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key]) delete next[key];
        });
        router.get('/admin/audit', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const columns: Array<Column<AuditEntity>> = [
        {
            key: 'action',
            header: 'Acción',
            cell: (row) => (
                <div>
                    <Badge tone="info">{row.action}</Badge>
                </div>
            ),
        },
        { key: 'user', header: 'Usuario', cell: (row) => <span className="text-fg">{row.user}</span> },
        {
            key: 'entity',
            header: 'Entidad',
            cell: (row) => (
                <span className="text-muted">
                    {row.entity_type ?? '—'}
                    {row.entity_id ? <span className="metric text-faint"> #{row.entity_id}</span> : null}
                </span>
            ),
        },
        { key: 'ip', header: 'IP', cell: (row) => <span className="metric text-muted">{row.ip_address ?? '—'}</span> },
        { key: 'date', header: 'Fecha', cell: (row) => <span className="text-muted">{formatDateTime(row.created_at)}</span> },
        {
            key: 'changes',
            header: 'Cambios',
            cell: (row) =>
                row.new_values ? (
                    <span className="metric truncate text-[11px] text-faint">{JSON.stringify(row.new_values).slice(0, 48)}</span>
                ) : (
                    <span className="text-faint">—</span>
                ),
        },
    ];

    return (
        <AdminLayout>
            <Head title="Auditoría" />

            <PageHeader title="Auditoría" description="Registro de acciones administrativas sobre la plataforma." />

            <div className="mt-6 rounded-card border border-line bg-card p-4">
                <FilterBar search={filters.search} onSearch={(value) => applyFilter({ search: value })} searchPlaceholder="Buscar por acción o entidad…" />

                <div className="mt-4">
                    <DataTable
                        columns={columns}
                        rows={logs.data}
                        keyExtractor={(row) => row.id}
                        empty={<EmptyState icon={ScrollText} title="Sin registros" />}
                    />
                </div>
                <Pagination paginator={logs} />
            </div>
        </AdminLayout>
    );
}
