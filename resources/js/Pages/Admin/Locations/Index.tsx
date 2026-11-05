import { Head, Link, router, useForm } from '@inertiajs/react';
import { MapPin, MonitorPlay, Pencil, Plus, Trash2 } from 'lucide-react';
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
import type { Option, Paginated } from '@/Types';
import type { CityEntity } from '@/Types/city';

interface OptionBusiness {
    id: number;
    name: string;
}

export default function LocationsIndex({
    locations,
    filters,
    options,
}: {
    locations: Paginated<CityEntity>;
    filters: { search?: string; status?: string; business_id?: string };
    options: { statuses: Option[]; businesses: OptionBusiness[] };
}) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<CityEntity | null>(null);
    const [deleting, setDeleting] = useState<CityEntity | null>(null);

    const form = useForm({
        business_ids: [] as number[],
        name: '',
        state: '',
        country: 'Colombia',
        timezone: 'America/Bogota',
        status: 'active',
    });

    const applyFilter = (patch: Record<string, string>) => {
        const next: Record<string, string> = { ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key]) delete next[key];
        });
        router.get('/admin/locations', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const openCreate = () => {
        form.reset();
        form.clearErrors();
        setEditing(null);
        setOpen(true);
    };

    const openEdit = (location: CityEntity) => {
        form.setData({
            business_ids: location.businesses.map((business) => business.id),
            name: location.name,
            state: location.state ?? '',
            country: location.country,
            timezone: location.timezone,
            status: location.status.value,
        });
        form.clearErrors();
        setEditing(location);
        setOpen(true);
    };

    const submit = () => {
        const config = {
            onSuccess: () => {
                setOpen(false);
                setEditing(null);
                form.reset();
            },
        };
        if (editing) {
            form.put(`/admin/locations/${editing.id}`, config);
        } else {
            form.post('/admin/locations', config);
        }
    };

    const columns: Array<Column<CityEntity>> = [
        {
            key: 'name',
            header: 'Ciudad',
            cell: (row) => (
                <div className="min-w-0">
                    <Link href={`/admin/locations/${row.id}`} className="block truncate font-medium text-fg hover:text-accent">
                        {row.name}
                    </Link>
                    <span className="block truncate text-xs text-faint">{row.businesses.map((business) => business.name).join(', ') || 'Sin negocios'}</span>
                </div>
            ),
        },
        { key: 'region', header: 'Región', cell: (row) => <span className="text-muted">{[row.state, row.country].filter(Boolean).join(', ')}</span> },
        {
            key: 'screens',
            header: 'Pantallas',
            cell: (row) => (
                <span className="inline-flex items-center gap-1.5 text-muted">
                    <MonitorPlay className="size-3.5 text-faint" />
                    <span className="metric text-positive">{row.online_devices_count}</span>
                    <span className="metric text-faint">/{row.devices_count}</span>
                </span>
            ),
        },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (row) => (
                <div className="flex justify-end">
                    <ActionMenu
                        items={[
                            { label: 'Ver detalle', icon: MapPin, onSelect: () => router.visit(`/admin/locations/${row.id}`) },
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
            <Head title="Ubicaciones · ciudades" />

            <PageHeader
                title="Ubicaciones · ciudades"
                description="Ciudades que agrupan negocios para segmentar campañas."
                actions={
                    <Button variant="primary" size="sm" onClick={openCreate}>
                        <Plus className="size-4" />
                        Nueva ciudad
                    </Button>
                }
            />

            <div className="mt-6 rounded-card border border-line bg-card p-4">
                <FilterBar search={filters.search} onSearch={(value) => applyFilter({ search: value })} searchPlaceholder="Buscar por ciudad o departamento…">
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
                </FilterBar>

                <div className="mt-4">
                    <DataTable
                        columns={columns}
                        rows={locations.data}
                        keyExtractor={(row) => row.id}
                        empty={<EmptyState icon={MapPin} title="Sin ciudades" />}
                    />
                </div>
                <Pagination paginator={locations} />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Editar ciudad' : 'Nueva ciudad'}</DialogTitle>
                    </DialogHeader>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField label="Negocios asignados" error={form.errors.business_ids} className="sm:col-span-2">
                            <p className="mb-2 text-xs text-muted">La campaña se reproducirá en todas las pantallas de los negocios seleccionados.</p>
                            <div className="max-h-48 space-y-2 overflow-y-auto rounded-control border border-line p-3">
                                {options.businesses.map((business) => (
                                    <label key={business.id} className="flex items-center gap-2 text-sm text-fg">
                                        <input type="checkbox" checked={form.data.business_ids.includes(business.id)}
                                            onChange={(event) => form.setData('business_ids', event.target.checked
                                                ? [...form.data.business_ids, business.id]
                                                : form.data.business_ids.filter((id) => id !== business.id))} />
                                        {business.name}
                                    </label>
                                ))}
                                {options.businesses.length === 0 ? <p className="text-xs text-muted">Crea un negocio antes de asignarlo.</p> : null}
                            </div>
                        </FormField>
                        <FormField label="Ciudad" error={form.errors.name}>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </FormField>
                        <FormField label="Departamento" error={form.errors.state}>
                            <Input value={form.data.state} onChange={(e) => form.setData('state', e.target.value)} />
                        </FormField>
                        <FormField label="País" error={form.errors.country}>
                            <Input value={form.data.country} onChange={(e) => form.setData('country', e.target.value)} />
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
                        <FormField label="Zona horaria" error={form.errors.timezone}>
                            <Input value={form.data.timezone} onChange={(e) => form.setData('timezone', e.target.value)} />
                        </FormField>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setOpen(false)}>
                            Cancelar
                        </Button>
                        <Button variant="primary" onClick={submit} disabled={form.processing}>
                            {editing ? 'Guardar cambios' : 'Crear ciudad'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(value) => (!value ? setDeleting(null) : null)}
                title={`Eliminar ${deleting?.name ?? ''}`}
                description="Se quitará esta ciudad de la segmentación. Los negocios, sucursales y pantallas se conservarán."
                confirmLabel="Eliminar ciudad"
                onConfirm={() => {
                    if (!deleting) return;
                    router.delete(`/admin/locations/${deleting.id}`, {
                        onSuccess: () => toast.success('Ciudad eliminada.'),
                    });
                }}
            />
        </AdminLayout>
    );
}
