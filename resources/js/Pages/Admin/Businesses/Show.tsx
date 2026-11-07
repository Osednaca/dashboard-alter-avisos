import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, Mail, MonitorPlay, Phone, Save, UserRound } from 'lucide-react';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { FormField, FormSection } from '@/Components/app/FormField';
import { PageHeader } from '@/Components/app/PageHeader';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { usePermissions } from '@/Hooks/usePermissions';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { BusinessEntity, DeviceEntity, Option } from '@/Types';
import { formatDate, formatRelative } from '@/Utils/format';

export default function BusinessShow({
    business,
    devices,
    options,
}: {
    business: BusinessEntity;
    devices: DeviceEntity[];
    options: { statuses: Option[]; categories: Option[] };
}) {
    const { can } = usePermissions();
    const form = useForm({
        name: business.name,
        category: business.category.value,
        status: business.status.value,
        timezone: business.timezone,
        contact_name: business.contact_name ?? '',
        contact_email: business.contact_email ?? '',
        contact_phone: business.contact_phone ?? '',
    });

    const deviceColumns: Array<Column<DeviceEntity>> = [
        {
            key: 'name', header: 'Aviso LED',
            cell: (row) => can('devices.view') ? (
                <Link href={`/admin/devices/${row.id}`} className="font-medium text-fg hover:text-accent">{row.name}</Link>
            ) : <span className="font-medium text-fg">{row.name}</span>,
        },
        { key: 'cities', header: 'Ciudades', cell: (row) => <span className="text-muted">{row.cities?.map((city) => city.name).join(', ') || 'Sin ciudad'}</span> },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'last_seen', header: 'Última señal', cell: (row) => <span className="text-muted">{formatRelative(row.last_seen_at)}</span> },
        { key: 'version', header: 'App', cell: (row) => <span className="metric text-muted">{row.app_version ?? '—'}</span> },
    ];

    return (
        <AdminLayout>
            <Head title={business.name} />
            <PageHeader
                eyebrow={business.category.label}
                title={business.name}
                description={`${business.devices_count} avisos LED · ${business.timezone}`}
                actions={<StatusBadge value={business.status} />}
            />

            <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card>
                    <CardContent className="flex items-center gap-3 pt-4">
                        <MonitorPlay className="size-5 text-accent" />
                        <div>
                            <p className="text-xs text-muted">Avisos LED</p>
                            <p className="metric text-xl text-fg">{business.devices_count}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="flex items-center gap-3 pt-4">
                        <MonitorPlay className="size-5 text-positive" />
                        <div>
                            <p className="text-xs text-muted">En línea</p>
                            <p className="metric text-xl text-fg">{business.online_devices_count}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="flex items-center gap-3 pt-4">
                        <Building2 className="size-5 text-info" />
                        <div>
                            <p className="text-xs text-muted">Creado</p>
                            <p className="text-sm text-fg">{formatDate(business.created_at)}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-4">
                <CardHeader><CardTitle>Contacto</CardTitle></CardHeader>
                <CardContent className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                    <p className="flex items-center gap-2 text-muted"><UserRound className="size-4 text-faint" /> {business.contact_name ?? '—'}</p>
                    <p className="flex items-center gap-2 text-muted"><Mail className="size-4 text-faint" /> {business.contact_email ?? '—'}</p>
                    <p className="flex items-center gap-2 text-muted"><Phone className="size-4 text-faint" /> {business.contact_phone ?? '—'}</p>
                </CardContent>
            </Card>

            <Card className="mt-4">
                <CardHeader><CardTitle>Avisos LED asignados</CardTitle></CardHeader>
                <CardContent>
                    <DataTable columns={deviceColumns} rows={devices} keyExtractor={(row) => row.id}
                        empty={<EmptyState icon={MonitorPlay} title="Sin avisos LED asignados" description="Asigna un negocio al activar o editar un aviso LED." />} />
                </CardContent>
            </Card>

            {can('businesses.edit') ? (
                <Card className="mt-4">
                    <CardContent className="pt-4">
                        <FormSection title="Información del negocio" description="Los cambios se registran en la auditoría.">
                            <FormField label="Nombre" error={form.errors.name}>
                                <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            </FormField>
                            <FormField label="Categoría" error={form.errors.category}>
                                <Select value={form.data.category} onValueChange={(value) => form.setData('category', value)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>{options.categories.map((category) => <SelectItem key={category.value} value={category.value}>{category.label}</SelectItem>)}</SelectContent>
                                </Select>
                            </FormField>
                            <FormField label="Estado" error={form.errors.status}>
                                <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>{options.statuses.map((status) => <SelectItem key={status.value} value={status.value}>{status.label}</SelectItem>)}</SelectContent>
                                </Select>
                            </FormField>
                            <FormField label="Zona horaria" error={form.errors.timezone}>
                                <Input value={form.data.timezone} onChange={(e) => form.setData('timezone', e.target.value)} />
                            </FormField>
                            <FormField label="Contacto" error={form.errors.contact_name}>
                                <Input value={form.data.contact_name} onChange={(e) => form.setData('contact_name', e.target.value)} />
                            </FormField>
                            <FormField label="Teléfono" error={form.errors.contact_phone}>
                                <Input value={form.data.contact_phone} onChange={(e) => form.setData('contact_phone', e.target.value)} />
                            </FormField>
                            <FormField label="Correo de contacto" error={form.errors.contact_email}>
                                <Input type="email" value={form.data.contact_email} onChange={(e) => form.setData('contact_email', e.target.value)} />
                            </FormField>
                        </FormSection>
                        <div className="mt-4 flex justify-end">
                            <Button variant="primary" onClick={() => form.put(`/admin/businesses/${business.id}`, { preserveScroll: true })} disabled={form.processing}>
                                <Save className="size-4" /> Guardar cambios
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            ) : null}
        </AdminLayout>
    );
}
