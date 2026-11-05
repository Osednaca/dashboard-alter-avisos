import { Head, Link, useForm } from '@inertiajs/react';
import {
    Building2,
    Mail,
    MapPin,
    MonitorPlay,
    Phone,
    PlayCircle,
    Save,
    Store,
    UserCog,
} from 'lucide-react';
import {
    Bar,
    CartesianGrid,
    ComposedChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { FormField, FormSection } from '@/Components/app/FormField';
import { PageHeader } from '@/Components/app/PageHeader';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type {
    BusinessEntity,
    CampaignEntity,
    DeviceEntity,
    LocationEntity,
    Option,
    SeriesPoint,
} from '@/Types';
import { formatCompact, formatDate, formatNumber } from '@/Utils/format';

interface PlaylistEntity {
    id: number;
    name: string;
    type: { value: string; label: string };
    status: { value: string; label: string };
    items_count: number;
    updated_at: string | null;
}

interface UserEntity {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    status?: { value: string; label: string };
    roles: string[];
}

export default function BusinessShow({
    business,
    locations,
    devices,
    playlists,
    campaigns,
    analytics,
    users,
    options,
}: {
    business: BusinessEntity;
    locations: LocationEntity[];
    devices: DeviceEntity[];
    playlists: PlaylistEntity[];
    campaigns: CampaignEntity[];
    analytics: { series: SeriesPoint[]; totals: { playbacks: number; completed: number; failures: number } };
    users: UserEntity[];
    options: { statuses: Option[]; categories: Option[] };
}) {
    const form = useForm({
        name: business.name,
        category: business.category.value,
        status: business.status.value,
        timezone: business.timezone,
        contact_name: business.contact_name ?? '',
        contact_email: business.contact_email ?? '',
        contact_phone: business.contact_phone ?? '',
    });

    const locationColumns: Array<Column<LocationEntity>> = [
        { key: 'name', header: 'Ubicación', cell: (row) => <span className="font-medium text-fg">{row.name}</span> },
        { key: 'city', header: 'Ciudad', cell: (row) => <span className="text-muted">{row.city}</span> },
        { key: 'address', header: 'Dirección', cell: (row) => <span className="text-muted">{row.address ?? '—'}</span> },
        {
            key: 'screens',
            header: 'Pantallas',
            cell: (row) => (
                <span className="metric text-fg">
                    {row.online_devices_count}
                    <span className="text-faint">/{row.devices_count}</span>
                </span>
            ),
        },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
    ];

    const deviceColumns: Array<Column<DeviceEntity>> = [
        { key: 'name', header: 'Pantalla', cell: (row) => <span className="font-medium text-fg">{row.name}</span> },
        { key: 'location', header: 'Ubicación', cell: (row) => <span className="text-muted">{row.location?.name ?? '—'}</span> },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'version', header: 'App', cell: (row) => <span className="metric text-muted">{row.app_version ?? '—'}</span> },
        { key: 'manifest', header: 'Manifiesto', cell: (row) => <span className="metric text-muted">{row.current_manifest_version ?? '—'}</span> },
    ];

    const campaignColumns: Array<Column<CampaignEntity>> = [
        {
            key: 'name',
            header: 'Campaña',
            cell: (row) => (
                <Link href={`/admin/campaigns/${row.id}`} className="font-medium text-fg hover:text-accent">
                    {row.name}
                </Link>
            ),
        },
        { key: 'advertiser', header: 'Anunciante', cell: (row) => <span className="text-muted">{row.advertiser?.name ?? '—'}</span> },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'screens', header: 'Pantallas', cell: (row) => <span className="metric text-fg">{row.target_screen_count}</span> },
    ];

    return (
        <AdminLayout>
            <Head title={business.name} />

            <PageHeader
                eyebrow={business.category.label}
                title={business.name}
                description={`${business.locations_count} ubicaciones · ${business.devices_count} pantallas · ${business.timezone}`}
                actions={<StatusBadge value={business.status} />}
            />

            <Tabs defaultValue="overview" className="mt-6">
                <TabsList>
                    <TabsTrigger value="overview">Resumen</TabsTrigger>
                    <TabsTrigger value="locations">Ubicaciones</TabsTrigger>
                    <TabsTrigger value="devices">Pantallas</TabsTrigger>
                    <TabsTrigger value="content">Contenido</TabsTrigger>
                    <TabsTrigger value="campaigns">Campañas</TabsTrigger>
                    <TabsTrigger value="analytics">Analíticas</TabsTrigger>
                    <TabsTrigger value="users">Usuarios</TabsTrigger>
                    <TabsTrigger value="settings">Configuración</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" className="mt-4 space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <Card>
                            <CardContent className="flex items-center gap-3 pt-4">
                                <Store className="size-5 text-accent" />
                                <div>
                                    <p className="text-xs text-muted">Ubicaciones</p>
                                    <p className="metric text-xl text-fg">{business.locations_count}</p>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="flex items-center gap-3 pt-4">
                                <MonitorPlay className="size-5 text-accent" />
                                <div>
                                    <p className="text-xs text-muted">Pantallas</p>
                                    <p className="metric text-xl text-fg">{business.devices_count}</p>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="flex items-center gap-3 pt-4">
                                <PlayCircle className="size-5 text-positive" />
                                <div>
                                    <p className="text-xs text-muted">Reproducciones (30 d)</p>
                                    <p className="metric text-xl text-fg">{formatCompact(analytics.totals.playbacks)}</p>
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

                    <Card>
                        <CardHeader>
                            <CardTitle>Contacto</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                            <p className="flex items-center gap-2 text-muted">
                                <UserCog className="size-4 text-faint" /> {business.contact_name ?? '—'}
                            </p>
                            <p className="flex items-center gap-2 text-muted">
                                <Mail className="size-4 text-faint" /> {business.contact_email ?? '—'}
                            </p>
                            <p className="flex items-center gap-2 text-muted">
                                <Phone className="size-4 text-faint" /> {business.contact_phone ?? '—'}
                            </p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="locations" className="mt-4">
                    <Card>
                        <CardContent className="pt-4">
                            <DataTable
                                columns={locationColumns}
                                rows={locations}
                                keyExtractor={(row) => row.id}
                                empty={<EmptyState icon={MapPin} title="Sin ubicaciones" />}
                            />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="devices" className="mt-4">
                    <Card>
                        <CardContent className="pt-4">
                            <DataTable
                                columns={deviceColumns}
                                rows={devices}
                                keyExtractor={(row) => row.id}
                                empty={<EmptyState icon={MonitorPlay} title="Sin pantallas" />}
                            />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="content" className="mt-4">
                    <Card>
                        <CardContent className="pt-4">
                            <DataTable
                                columns={[
                                    { key: 'name', header: 'Lista', cell: (row: PlaylistEntity) => <span className="font-medium text-fg">{row.name}</span> },
                                    { key: 'type', header: 'Tipo', cell: (row: PlaylistEntity) => <span className="text-muted">{row.type.label}</span> },
                                    { key: 'items', header: 'Elementos', cell: (row: PlaylistEntity) => <span className="metric text-fg">{row.items_count}</span> },
                                    { key: 'status', header: 'Estado', cell: (row: PlaylistEntity) => <StatusBadge value={row.status} /> },
                                ]}
                                rows={playlists}
                                keyExtractor={(row) => row.id}
                                empty={<EmptyState icon={PlayCircle} title="Sin listas de reproducción" />}
                            />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="campaigns" className="mt-4">
                    <Card>
                        <CardContent className="pt-4">
                            <DataTable
                                columns={campaignColumns}
                                rows={campaigns}
                                keyExtractor={(row) => row.id}
                                empty={<EmptyState icon={Building2} title="Sin campañas asociadas" />}
                            />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="analytics" className="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Reproducciones de los últimos 30 días</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {analytics.series.length === 0 ? (
                                <EmptyState icon={PlayCircle} title="Sin datos de reproducción" />
                            ) : (
                                <ResponsiveContainer width="100%" height={260}>
                                    <ComposedChart data={analytics.series} margin={{ top: 8, right: 8, left: -18, bottom: 0 }}>
                                        <CartesianGrid stroke="#1b3042" vertical={false} />
                                        <XAxis dataKey="label" tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} minTickGap={20} />
                                        <YAxis tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} tickFormatter={(v) => formatCompact(v as number)} />
                                        <Tooltip
                                            contentStyle={{ background: '#0d1c2b', border: '1px solid #1b3042', borderRadius: 10, fontSize: 12 }}
                                            formatter={(value: number, name: string) => [formatNumber(value), name]}
                                        />
                                        <Bar dataKey="playbacks" name="Reproducciones" fill="#ffc83d" radius={[3, 3, 0, 0]} />
                                    </ComposedChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="users" className="mt-4">
                    <Card>
                        <CardContent className="pt-4">
                            <DataTable
                                columns={[
                                    { key: 'name', header: 'Nombre', cell: (row: UserEntity) => <span className="font-medium text-fg">{row.name}</span> },
                                    { key: 'email', header: 'Correo', cell: (row: UserEntity) => <span className="text-muted">{row.email}</span> },
                                    { key: 'job', header: 'Cargo', cell: (row: UserEntity) => <span className="text-muted">{row.job_title ?? '—'}</span> },
                                    { key: 'roles', header: 'Roles', cell: (row: UserEntity) => <span className="text-muted">{row.roles.join(', ') || '—'}</span> },
                                ]}
                                rows={users}
                                keyExtractor={(row) => row.id}
                                empty={<EmptyState icon={UserCog} title="Sin usuarios asignados" />}
                            />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="settings" className="mt-4">
                    <Card>
                        <CardContent className="pt-4">
                            <FormSection title="Información del negocio" description="Los cambios se registran en la auditoría.">
                                <FormField label="Nombre" error={form.errors.name}>
                                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
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
                                <FormField label="Zona horaria" error={form.errors.timezone}>
                                    <Input value={form.data.timezone} onChange={(e) => form.setData('timezone', e.target.value)} />
                                </FormField>
                            </FormSection>
                            <div className="mt-4 flex justify-end">
                                <Button
                                    variant="primary"
                                    onClick={() => form.put(`/admin/businesses/${business.id}`, { preserveScroll: true })}
                                    disabled={form.processing}
                                >
                                    <Save className="size-4" />
                                    Guardar cambios
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </AdminLayout>
    );
}
