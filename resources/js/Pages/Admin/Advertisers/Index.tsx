import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2, Eye, Pencil, PlayCircle, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ActionMenu } from '@/Components/app/ActionMenu';
import { ConfirmDialog } from '@/Components/app/ConfirmDialog';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { FilterBar } from '@/Components/app/FilterBar';
import { FormField } from '@/Components/app/FormField';
import { PageHeader } from '@/Components/app/PageHeader';
import { Pagination } from '@/Components/app/Pagination';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { AdvertiserEntity, Option, Paginated } from '@/Types';
import { formatCompact } from '@/Utils/format';

export default function AdvertisersIndex({
    advertisers,
    filters,
    options,
}: {
    advertisers: Paginated<AdvertiserEntity>;
    filters: { search?: string; status?: string };
    options: { statuses: Option[] };
}) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<AdvertiserEntity | null>(null);
    const [deleting, setDeleting] = useState<AdvertiserEntity | null>(null);

    const form = useForm({
        name: '',
        status: 'prospect',
        contact_name: '',
        contact_email: '',
        contact_phone: '',
        billing_name: '',
        billing_tax_id: '',
        billing_email: '',
        billing_address: '',
    });

    const applyFilter = (patch: Record<string, string>) => {
        const next: Record<string, string> = { ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key]) delete next[key];
        });
        router.get('/admin/advertisers', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const openCreate = () => {
        form.reset();
        form.clearErrors();
        setEditing(null);
        setOpen(true);
    };

    const openEdit = (advertiser: AdvertiserEntity) => {
        form.setData({
            name: advertiser.name,
            status: advertiser.status.value,
            contact_name: advertiser.contact_name ?? '',
            contact_email: advertiser.contact_email ?? '',
            contact_phone: advertiser.contact_phone ?? '',
            billing_name: advertiser.billing_name ?? '',
            billing_tax_id: advertiser.billing_tax_id ?? '',
            billing_email: advertiser.billing_email ?? '',
            billing_address: advertiser.billing_address ?? '',
        });
        form.clearErrors();
        setEditing(advertiser);
    };

    const submit = () => {
        const config = {
            onSuccess: () => {
                setOpen(false);
                setEditing(null);
                form.reset();
            },
        };
        if (editing) form.put(`/admin/advertisers/${editing.id}`, config);
        else form.post('/admin/advertisers', config);
    };

    const columns: Array<Column<AdvertiserEntity>> = [
        {
            key: 'name',
            header: 'Anunciante',
            cell: (row) => (
                <div className="flex items-center gap-3">
                    <span className="flex size-9 items-center justify-center rounded-control border border-line bg-surface text-accent">
                        <Building2 className="size-4" />
                    </span>
                    <div className="min-w-0">
                        <Link href={`/admin/advertisers/${row.id}`} className="block truncate font-medium text-fg hover:text-accent">
                            {row.name}
                        </Link>
                        <span className="block truncate text-xs text-faint">{row.contact_email ?? row.slug}</span>
                    </div>
                </div>
            ),
        },
        { key: 'campaigns', header: 'Campañas activas', cell: (row) => <span className="metric text-fg">{row.active_campaigns_count}</span> },
        { key: 'screens', header: 'Pantallas alcanzadas', cell: (row) => <span className="metric text-fg">{row.total_screens}</span> },
        {
            key: 'playbacks',
            header: 'Reproducciones',
            cell: (row) => (
                <span className="inline-flex items-center gap-1.5">
                    <PlayCircle className="size-3.5 text-faint" />
                    <span className="metric text-fg">{formatCompact(row.total_playbacks)}</span>
                </span>
            ),
        },
        { key: 'contact', header: 'Contacto', cell: (row) => <span className="text-muted">{row.contact_name ?? '—'}</span> },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (row) => (
                <div className="flex justify-end">
                    <ActionMenu
                        items={[
                            { label: 'Ver detalle', icon: Eye, onSelect: () => router.visit(`/admin/advertisers/${row.id}`) },
                            { label: 'Editar', icon: Pencil, onSelect: () => openEdit(row) },
                            { label: 'Eliminar', icon: Trash2, variant: 'danger', separatorBefore: true, onSelect: () => setDeleting(row) },
                        ]}
                    />
                </div>
            ),
        },
    ];

    return (
        <AdminLayout>
            <Head title="Anunciantes" />

            <PageHeader
                title="Anunciantes"
                description="Marcas y clientes que pautan en la red."
                actions={
                    <Button variant="primary" size="sm" onClick={openCreate}>
                        <Plus className="size-4" />
                        Nuevo anunciante
                    </Button>
                }
            />

            <div className="mt-6 rounded-card border border-line bg-card p-4">
                <FilterBar search={filters.search} onSearch={(value) => applyFilter({ search: value })} searchPlaceholder="Buscar anunciante…">
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
                </FilterBar>

                <div className="mt-4">
                    <DataTable
                        columns={columns}
                        rows={advertisers.data}
                        keyExtractor={(row) => row.id}
                        empty={<EmptyState icon={Building2} title="Sin anunciantes" />}
                    />
                </div>
                <Pagination paginator={advertisers} />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Editar anunciante' : 'Nuevo anunciante'}</DialogTitle>
                    </DialogHeader>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField label="Nombre" error={form.errors.name} className="sm:col-span-2">
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </FormField>
                        <FormField label="Estado" error={form.errors.status}>
                            <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.statuses.map((status) => (
                                        <SelectItem key={status.value} value={status.value}>
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField label="Teléfono" error={form.errors.contact_phone}>
                            <Input value={form.data.contact_phone} onChange={(e) => form.setData('contact_phone', e.target.value)} />
                        </FormField>
                        <FormField label="Contacto" error={form.errors.contact_name}>
                            <Input value={form.data.contact_name} onChange={(e) => form.setData('contact_name', e.target.value)} />
                        </FormField>
                        <FormField label="Correo de contacto" error={form.errors.contact_email}>
                            <Input type="email" value={form.data.contact_email} onChange={(e) => form.setData('contact_email', e.target.value)} />
                        </FormField>
                        <FormField label="Razón social" error={form.errors.billing_name}>
                            <Input value={form.data.billing_name} onChange={(e) => form.setData('billing_name', e.target.value)} />
                        </FormField>
                        <FormField label="NIT" error={form.errors.billing_tax_id}>
                            <Input value={form.data.billing_tax_id} onChange={(e) => form.setData('billing_tax_id', e.target.value)} />
                        </FormField>
                        <FormField label="Correo de facturación" error={form.errors.billing_email}>
                            <Input type="email" value={form.data.billing_email} onChange={(e) => form.setData('billing_email', e.target.value)} />
                        </FormField>
                        <FormField label="Dirección de facturación" error={form.errors.billing_address}>
                            <Input value={form.data.billing_address} onChange={(e) => form.setData('billing_address', e.target.value)} />
                        </FormField>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setOpen(false)}>
                            Cancelar
                        </Button>
                        <Button variant="primary" onClick={submit} disabled={form.processing}>
                            {editing ? 'Guardar cambios' : 'Crear anunciante'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(value) => (!value ? setDeleting(null) : null)}
                title={`Eliminar ${deleting?.name ?? ''}`}
                description="Se eliminarán sus campañas asociadas."
                confirmLabel="Eliminar anunciante"
                onConfirm={() => {
                    if (!deleting) return;
                    router.delete(`/admin/advertisers/${deleting.id}`, { onSuccess: () => toast.success('Anunciante eliminado.') });
                }}
            />
        </AdminLayout>
    );
}
