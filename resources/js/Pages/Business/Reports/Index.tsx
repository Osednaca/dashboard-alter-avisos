import { Head, router } from '@inertiajs/react';
import { Activity, Clock, MonitorPlay, PlayCircle, RefreshCw, TrendingUp, XCircle } from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { DateRangePicker } from '@/Components/app/DateRangePicker';
import { EmptyState } from '@/Components/app/EmptyState';
import { PageHeader } from '@/Components/app/PageHeader';
import { StatCard } from '@/Components/app/StatCard';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { BusinessLayout } from '@/Layouts/BusinessLayout';
import type { EnumValue, SeriesPoint } from '@/Types';
import { formatCompact, formatNumber, formatRelative } from '@/Utils/format';

interface ReportRow {
    name: string;
    playbacks: number;
    completed?: number;
    uptime_hours?: number;
}

export default function ReportsIndex({
    range,
    filters,
    metrics,
    series,
    topMedia,
    perScreen,
    uptime,
    syncHealth,
    options,
}: {
    range: { from: string; to: string };
    filters: Record<string, string | undefined>;
    metrics: {
        playbacks: number;
        completed: number;
        completionRate: number;
        failures: number;
        durationHours: number;
        activeScreens: number;
        totalScreens: number;
    };
    series: SeriesPoint[];
    topMedia: Array<{ filename: string; type: string; playbacks: number; duration: number }>;
    perScreen: ReportRow[];
    uptime: ReportRow[];
    syncHealth: Array<{ name: string; location: string | null; status: EnumValue; last_sync_at: string | null }>;
    options: {
        devices: Array<{ id: number; name: string }>;
        locations: Array<{ id: number; name: string }>;
        playlists: Array<{ id: number; name: string }>;
    };
}) {
    const applyFilter = (patch: Record<string, string>) => {
        const next: Record<string, string> = { from: range.from, to: range.to, ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key]) delete next[key];
        });
        router.get('/business/reports', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const screenColumns: Array<Column<ReportRow>> = [
        { key: 'name', header: 'Pantalla', cell: (row) => <span className="font-medium text-fg">{row.name}</span> },
        { key: 'playbacks', header: 'Reproducciones', cell: (row) => <span className="metric text-fg">{formatNumber(row.playbacks)}</span> },
        { key: 'completed', header: 'Completadas', cell: (row) => <span className="metric text-muted">{formatNumber(row.completed ?? 0)}</span> },
    ];

    const uptimeColumns: Array<Column<ReportRow>> = [
        { key: 'name', header: 'Pantalla', cell: (row) => <span className="font-medium text-fg">{row.name}</span> },
        { key: 'uptime', header: 'Encendido', cell: (row) => <span className="metric text-fg">{row.uptime_hours ?? 0} h</span> },
        { key: 'playbacks', header: 'Reproducciones', cell: (row) => <span className="metric text-muted">{formatNumber(row.playbacks)}</span> },
    ];

    const maxTop = Math.max(...topMedia.map((item) => item.playbacks), 1);

    return (
        <BusinessLayout>
            <Head title="Reportes" />

            <PageHeader
                eyebrow="Analítica"
                title="Reportes"
                description="Rendimiento de tu contenido, pantallas y sincronización."
                actions={
                    <DateRangePicker
                        from={range.from}
                        to={range.to}
                        routeName="business.reports"
                        extra={filters as Record<string, string>}
                    />
                }
            />

            <div className="mt-6 flex flex-wrap gap-2">
                <Select value={filters.device_id ?? 'all'} onValueChange={(value) => applyFilter({ device_id: value === 'all' ? '' : value })}>
                    <SelectTrigger className="w-full sm:w-44">
                        <SelectValue placeholder="Pantalla" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas las pantallas</SelectItem>
                        {options.devices.map((device) => (
                            <SelectItem key={device.id} value={String(device.id)}>
                                {device.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select value={filters.location_id ?? 'all'} onValueChange={(value) => applyFilter({ location_id: value === 'all' ? '' : value })}>
                    <SelectTrigger className="w-full sm:w-44">
                        <SelectValue placeholder="Ubicación" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas las ubicaciones</SelectItem>
                        {options.locations.map((location) => (
                            <SelectItem key={location.id} value={String(location.id)}>
                                {location.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select value={filters.playlist_id ?? 'all'} onValueChange={(value) => applyFilter({ playlist_id: value === 'all' ? '' : value })}>
                    <SelectTrigger className="w-full sm:w-48">
                        <SelectValue placeholder="Playlist" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas las playlists</SelectItem>
                        {options.playlists.map((playlist) => (
                            <SelectItem key={playlist.id} value={String(playlist.id)}>
                                {playlist.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-4 xl:grid-cols-4">
                <StatCard label="Reproducciones" value={metrics.playbacks} icon={Activity} />
                <StatCard label="Tasa de finalización" value={`${metrics.completionRate}%`} icon={TrendingUp} delay={80} />
                <StatCard label="Tiempo reproducido" value={`${metrics.durationHours} h`} icon={Clock} delay={160} />
                <StatCard label="Pantallas activas" value={`${metrics.activeScreens} / ${metrics.totalScreens}`} icon={MonitorPlay} delay={240} />
            </div>
            <div className="mt-4 grid grid-cols-2 gap-4 xl:grid-cols-4">
                <StatCard label="Reproducciones completadas" value={metrics.completed} icon={PlayCircle} />
                <StatCard label="Fallos" value={metrics.failures} icon={XCircle} delay={80} />
                <StatCard label="Pantallas reportando" value={metrics.activeScreens} icon={RefreshCw} delay={160} />
                <StatCard label="Contenido activo" value={topMedia.length} icon={PlayCircle} delay={240} />
            </div>

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle>Reproducciones del contenido propio por día</CardTitle>
                </CardHeader>
                <CardContent>
                    {series.length === 0 ? (
                        <EmptyState icon={Activity} title="Sin datos en el periodo" />
                    ) : (
                        <ResponsiveContainer width="100%" height={280}>
                            <BarChart data={series} margin={{ top: 8, right: 8, left: -18, bottom: 0 }}>
                                <CartesianGrid stroke="#1b3042" vertical={false} />
                                <XAxis dataKey="label" tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} minTickGap={20} />
                                <YAxis tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} tickFormatter={(value) => formatCompact(value as number)} />
                                <Tooltip
                                    contentStyle={{ background: '#0d1c2b', border: '1px solid #1b3042', borderRadius: 10, fontSize: 12 }}
                                    formatter={(value: number, name: string) => [formatNumber(value), name]}
                                />
                                <Bar dataKey="playbacks" name="Reproducciones" fill="#ffc83d" radius={[3, 3, 0, 0]} />
                                <Bar dataKey="completed" name="Completadas" fill="#35d07f" radius={[3, 3, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </CardContent>
            </Card>

            <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Contenido más reproducido</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {topMedia.length === 0 ? (
                            <EmptyState icon={PlayCircle} title="Sin datos" />
                        ) : (
                            topMedia.map((item) => (
                                <div key={item.filename} className="space-y-1">
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="truncate text-fg">{item.filename}</span>
                                        <span className="metric text-muted">{formatNumber(item.playbacks)}</span>
                                    </div>
                                    <div className="h-1.5 overflow-hidden rounded-full bg-elevated">
                                        <div className="h-full rounded-full bg-accent" style={{ width: `${(item.playbacks / maxTop) * 100}%` }} />
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Reproducción por pantalla</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable columns={screenColumns} rows={perScreen} keyExtractor={(row) => row.name} empty={<EmptyState icon={MonitorPlay} title="Sin datos" />} />
                    </CardContent>
                </Card>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Tiempo encendido por pantalla</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable columns={uptimeColumns} rows={uptime} keyExtractor={(row) => row.name} empty={<EmptyState icon={Clock} title="Sin datos" />} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Estado de sincronización</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y divide-line">
                            {syncHealth.map((device) => (
                                <li key={device.name} className="flex items-center justify-between py-2 text-xs">
                                    <span className="min-w-0">
                                        <span className="block truncate text-fg">{device.name}</span>
                                        <span className="block truncate text-[10px] text-faint">{device.location ?? 'Sin ubicación'}</span>
                                    </span>
                                    <span className="flex items-center gap-3">
                                        <span className="text-muted">{formatRelative(device.last_sync_at)}</span>
                                        <StatusBadge value={device.status} dot={false} />
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </BusinessLayout>
    );
}
