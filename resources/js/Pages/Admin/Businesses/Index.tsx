import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2, Eye, MapPin, MonitorPlay, Pencil, Plus, Trash2 } from 'lucide-react';
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
import type { BusinessEntity, Option, Paginated } from '@/Types';
import { formatNumber } from '@/Utils/format';

interface Filters {
    search?: string;
    status?: string;
    category?: string;
    city?: string;
    sort?: string;
    direction?: string;
}

interface Options {
    statuses: Option[];
    categories: Option[];
    cities: string[];
}

export default function BusinessesIndex({
    businesses,
    filters,
    options,
}: {
    businesses: Paginated<BusinessEntity>;
    filters: Filters;
    options: Options;
}) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<BusinessEntity | null>(null);
    const [deleting, setDeleting] = useState<BusinessEntity | null>(null);

    const form = useForm({
        name: '',
        category: 'restaurant',
        status: 'onboarding',
        timezone: 'America/Bogota',
        contact_name: '',
        contact_email: '',
        contact_phone: '',
    });

    const applyFilter = (patch: Partial<Filters>) => {
        const next = { ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key as keyof Filters]) delete next[key as keyof Filters];
        });
        router.get('/admin/businesses', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const openCreate = () => {
        form.reset();
        form.clearErrors();
        setEditing(null);
        setCreating(true);
    };

    const openEdit = (business: BusinessEntity) => {
        form.setData({
            name: business.name,
            category: business.category.value,
            status: business.status.value,
            timezone: business.timezone,
            contact_name: business.contact_name ?? '',
            contact_email: business.contact_email ?? '',
            contact_phone: business.contact_phone ?? '',
        });
        form.clearErrors();
        setEditing(business);
    };

    const submit = () => {
        const options = {
            onSuccess: () => {
                setCreating(false);
                setEditing(null);
                form.reset();
            },
        };

        if (editing) {
            form.put(`/admin/businesses/${editing.id}`, options);
        } else {
            form.post('/admin/businesses', options);
        }
    };

    const columns: Array<Column<BusinessEntity>> = [
        {
            key: 'name',
            header: 'Negocio',
            cell: (row) => (
                <div className="flex items-center gap-3">
                    <span className="flex size-9 items-center justify-center rounded-control border border-line bg-surface text-accent">
                        <Building2 className="size-4" />
                    </span>
                    <div className="min-w-0">
                        <Link href={`/admin/businesses/${row.id}`} className="block truncate font-medium text-fg hover:text-accent">
                            {row.name}
                        </Link>
                        <span className="block truncate text-xs text-faint">{row.contact_name ?? row.slug}</span>
                    </div>
                </div>
            ),
        },
        { key: 'category', header: 'Categoría', cell: (row) => <span className="text-muted">{row.category.label}</span> },
        {
            key: 'locations',
            header: 'Ubicaciones',
            cell: (row) => (
                <span className="inline-flex items-center gap-1.5 text-muted">
                    <MapPin className="size-3.5 text-faint" />
                    {row.locations_count}
                </span>
            ),
        },
        {
            key: 'devices',
            header: 'Pantallas',
            cell: (row) => (
                <span className="inline-flex items-center gap-1.5 text-muted">
                    <MonitorPlay className="size-3.5 text-faint" />
                    {formatNumber(row.devices_count)}
                </span>
            ),
        },
        {
            key: 'online',
            header: 'En línea',
            cell: (row) => (
                <span className="metric text-positive">
                    {row.online_devices_count}
                    <span className="text-faint">/{row.devices_count}</span>
                </span>
            ),
        },
        {
            key: 'layout',
            header: 'Layout actual',
            cell: (row) =>
                row.current_layout ? (
                    <span className="text-muted">
                        {row.current_layout.name}{' '}
                        <span className="metric text-faint">{row.current_layout.ratio}</span>
                    </span>
                ) : (
                    <span className="text-faint">—</span>
                ),
        },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        {
            key: 'actions',
            header: '',
            headerClassName: 'text-right',
            className: 'text-right',
            cell: (row) => (
                <div className="flex justify-end">
                    <ActionMenu
                        items={[
                            { label: 'Ver detalle', icon: Eye, onSelect: () => router.visit(`/admin/businesses/${row.id}`) },
                            { label: 'Editar', icon: Pencil, onSelect: () => openEdit(row) },
                            {
                                label: 'Eliminar',
                                icon: Trash2,
                                variant: 'danger',
                                separatorBefore: true,
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
            <Head title="Negocios" />

            <PageHeader
                title="Negocios"
                description="Empresas que operan pantallas en la red."
                actions={
                    <Button variant="primary" size="sm" onClick={openCreate}>
                        <Plus className="size-4" />
                        Nuevo negocio
                    </Button>
                }
            />

            <div className="mt-6 rounded-card border border-line bg-card p-4">
                <FilterBar
                    search={filters.search}
                    onSearch={(value) => applyFilter({ search: value })}
                    searchPlaceholder="Buscar por nombre o contacto…"
                >
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
                </FilterBar>

                <div className="mt-4">
                    <DataTable
                        columns={columns}
                        rows={businesses.data}
                        keyExtractor={(row) => row.id}
                        empty={
                            <EmptyState
                                icon={Building2}
                                title="Sin negocios"
                                description="Ajusta los filtros o crea el primer negocio."
                            />
                        }
                    />
                </div>

                <Pagination paginator={businesses} />
            </div>

            <Dialog open={creating || !!editing} onOpenChange={(open) => (!open ? (setCreating(false), setEditing(null)) : null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Editar negocio' : 'Nuevo negocio'}</DialogTitle>
                    </DialogHeader>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField label="Nombre" error={form.errors.name} className="sm:col-span-2">
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Restaurante La Hamburguesería" />
                        </FormField>
                        <FormField label="Categoría" error={form.errors.category}>
                            <Select value={form.data.category} onValueChange={(value) => form.setData('category', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.categories.map((category) => (
                                        <SelectItem key={category.value} value={category.value}>
                                            {category.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
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
                        <FormField label="Contacto" error={form.errors.contact_name}>
                            <Input value={form.data.contact_name} onChange={(e) => form.setData('contact_name', e.target.value)} />
                        </FormField>
                        <FormField label="Teléfono" error={form.errors.contact_phone}>
                            <Input value={form.data.contact_phone} onChange={(e) => form.setData('contact_phone', e.target.value)} placeholder="+57 300 000 0000" />
                        </FormField>
                        <FormField label="Correo de contacto" error={form.errors.contact_email} className="sm:col-span-2">
                            <Input type="email" value={form.data.contact_email} onChange={(e) => form.setData('contact_email', e.target.value)} />
                        </FormField>
                        <FormField label="Zona horaria" error={form.errors.timezone} className="sm:col-span-2">
                            <Input value={form.data.timezone} onChange={(e) => form.setData('timezone', e.target.value)} />
                        </FormField>
                    </div>

                    <DialogFooter>
                        <Button variant="ghost" onClick={() => (setCreating(false), setEditing(null))}>
                            Cancelar
                        </Button>
                        <Button variant="primary" onClick={submit} disabled={form.processing}>
                            {editing ? 'Guardar cambios' : 'Crear negocio'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(open) => (!open ? setDeleting(null) : null)}
                title={`Eliminar ${deleting?.name ?? ''}`}
                description="Se eliminarán sus ubicaciones, pantallas y contenido asociado. Esta acción no se puede deshacer."
                confirmLabel="Eliminar negocio"
                onConfirm={() => {
                    if (!deleting) return;
                    router.delete(`/admin/businesses/${deleting.id}`, {
                        onSuccess: () => toast.success('Negocio eliminado.'),
                    });
                }}
            />
        </AdminLayout>
    );
}
