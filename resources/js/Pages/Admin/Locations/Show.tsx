import { Head, Link } from '@inertiajs/react';
import { MapPin, MonitorPlay } from 'lucide-react';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { PageHeader } from '@/Components/app/PageHeader';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Card, CardContent } from '@/Components/ui/card';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { DeviceEntity } from '@/Types';
import type { CityEntity } from '@/Types/city';
import { formatRelative } from '@/Utils/format';

export default function LocationShow({
    location,
    devices,
}: {
    location: CityEntity;
    devices: DeviceEntity[];
}) {
    const columns: Array<Column<DeviceEntity>> = [
        {
            key: 'name',
            header: 'Pantalla',
            cell: (row) => (
                <Link href={`/admin/devices/${row.id}`} className="font-medium text-fg hover:text-accent">
                    {row.name}
                </Link>
            ),
        },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'version', header: 'App', cell: (row) => <span className="metric text-muted">{row.app_version ?? '—'}</span> },
        { key: 'last_seen', header: 'Última señal', cell: (row) => <span className="text-muted">{formatRelative(row.last_seen_at)}</span> },
        { key: 'layout', header: 'Layout', cell: (row) => <span className="text-muted">{row.current_layout?.name ?? '—'}</span> },
    ];

    return (
        <AdminLayout>
            <Head title={location.name} />

            <PageHeader
                eyebrow="Ubicación · ciudad"
                title={location.name}
                description={[location.state, location.country].filter(Boolean).join(', ')}
                actions={<StatusBadge value={location.status} />}
            />

            <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card>
                    <CardContent className="flex items-center gap-3 pt-4">
                        <MapPin className="size-5 text-accent" />
                        <div>
                            <p className="text-xs text-muted">Ciudad</p>
                            <p className="text-sm text-fg">{location.name}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="flex items-center gap-3 pt-4">
                        <MonitorPlay className="size-5 text-accent" />
                        <div>
                            <p className="text-xs text-muted">Pantallas</p>
                            <p className="metric text-sm text-fg">
                                {location.online_devices_count}/{location.devices_count} en línea
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-4">
                        <p className="text-xs text-muted">Zona horaria</p>
                        <p className="text-sm text-fg">{location.timezone}</p>
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-4">
                <CardContent className="pt-4">
                    <p className="mb-2 text-sm font-medium text-fg">Negocios asignados ({location.businesses.length})</p>
                    <div className="flex flex-wrap gap-3">
                        {location.businesses.map((business) => <Link key={business.id} href={`/admin/businesses/${business.id}`} className="text-sm text-accent">{business.name}</Link>)}
                        {location.businesses.length === 0 ? <p className="text-xs text-muted">Sin negocios asignados.</p> : null}
                    </div>
                </CardContent>
            </Card>
            <Card className="mt-4">
                <CardContent className="pt-4">
                    <DataTable
                        columns={columns}
                        rows={devices}
                        keyExtractor={(row) => row.id}
                        empty={<EmptyState icon={MonitorPlay} title="Sin pantallas en los negocios asignados" />}
                    />
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
