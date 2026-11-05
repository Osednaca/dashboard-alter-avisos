import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    BarChart3,
    Building2,
    Megaphone,
    MonitorPlay,
    Plus,
    RefreshCw,
    ShieldCheck,
    TriangleAlert,
    Wifi,
} from 'lucide-react';
import {
    Area,
    Bar,
    CartesianGrid,
    Cell,
    ComposedChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartCard } from '@/Components/app/ChartCard';
import { DateRangePicker } from '@/Components/app/DateRangePicker';
import { EmptyState } from '@/Components/app/EmptyState';
import { PageHeader } from '@/Components/app/PageHeader';
import { ScreenPreview, type ScreenPreviewData } from '@/Components/app/ScreenPreview';
import { StatCard } from '@/Components/app/StatCard';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { usePermissions } from '@/Hooks/usePermissions';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { AlertEntity, AuditEntity, CampaignEntity, PageProps, SeriesPoint } from '@/Types';
import { formatCompact, formatNumber, formatRelative } from '@/Utils/format';

interface Overview {
    range: { from: string; to: string };
    kpis: {
        activeScreens: { value: number; total: number; breakdown: Record<string, number> };
        businesses: { value: number; total: number };
        campaigns: { value: number; total: number };
        playbacksToday: { value: number; trend: number | null };
    };
    totals: {
        current: { playbacks: number; completed: number; failures: number; duration: number };
        previous: { playbacks: number; completed: number; failures: number; duration: number };
        playbackTrend: number | null;
    };
    series: SeriesPoint[];
    deviceHealth: { total: number; online: number; offline: number; maintenance: number; disabled: number };
    cities: Array<{ city: string; screens: number; online: number }>;
    cityCoverage: number;
    campaignPerformance: CampaignEntity[];
    recentActivity: { logs: AuditEntity[]; alerts: AlertEntity[] };
}

const healthColors: Record<string, string> = {
    online: '#35d07f',
    offline: '#ff5a5f',
    maintenance: '#ffb020',
    disabled: '#5f7488',
};

export default function Dashboard({ overview, screenPreview, previewDevices }: {
    overview: Overview;
    screenPreview: ScreenPreviewData | null;
    previewDevices: Array<{ id: number; name: string }>;
}) {
    usePoll(3000, { only: ['screenPreview'] });
    const { auth } = usePage<PageProps>().props;
    const { can } = usePermissions();
    const firstName = (auth.user?.name ?? '').split(' ')[0];

    const health = [
        { key: 'online', label: 'En línea', value: overview.deviceHealth.online },
        { key: 'offline', label: 'Desconectadas', value: overview.deviceHealth.offline },
        { key: 'maintenance', label: 'Mantenimiento', value: overview.deviceHealth.maintenance },
        { key: 'disabled', label: 'Deshabilitadas', value: overview.deviceHealth.disabled },
    ].filter((item) => item.value > 0);

    const healthTotal = overview.deviceHealth.total || 1;
    const onlinePercent = Math.round((overview.deviceHealth.online / healthTotal) * 100);

    const goToDevices = (status?: string) =>
        router.get('/admin/devices', status ? { status } : {});

    return (
        <AdminLayout>
            <Head title="Dashboard" />

            <PageHeader
                eyebrow="Centro de operaciones"
                title={`¡Hola, ${firstName}!`}
                description="Estado de la red de pantallas, entrega de campañas y salud de dispositivos."
                actions={
                    <>
                        <DateRangePicker
                            from={overview.range.from}
                            to={overview.range.to}
                            routeName="dashboard"
                            extra={{ device: screenPreview?.device.id }}
                        />
                        {can('campaigns.create') ? (
                            <Button variant="primary" size="sm" asChild>
                                <Link href="/admin/campaigns/create">
                                    <Plus className="size-4" />
                                    Nueva campaña
                                </Link>
                            </Button>
                        ) : null}
                    </>
                }
            />

            <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Pantallas activas"
                    value={overview.kpis.activeScreens.value}
                    total={overview.kpis.activeScreens.total}
                    hint="pantallas"
                    icon={MonitorPlay}
                    meter={(overview.kpis.activeScreens.value / (overview.kpis.activeScreens.total || 1)) * 100}
                />
                <StatCard
                    label="Negocios conectados"
                    value={overview.kpis.businesses.value}
                    total={overview.kpis.businesses.total}
                    hint="negocios"
                    icon={Building2}
                    delay={80}
                />
                <StatCard
                    label="Campañas en curso"
                    value={overview.kpis.campaigns.value}
                    total={overview.kpis.campaigns.total}
                    hint="campañas"
                    icon={Megaphone}
                    delay={160}
                />
                <StatCard
                    label="Reproducciones hoy"
                    value={overview.kpis.playbacksToday.value}
                    trend={overview.kpis.playbacksToday.trend}
                    hint="vs. ayer"
                    icon={Activity}
                    delay={240}
                />
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-12">
                <ChartCard
                    className="xl:col-span-8"
                    title="Reproducciones en el tiempo"
                    subtitle={`${formatNumber(overview.totals.current.playbacks)} reproducciones · ${overview.totals.playbackTrend !== null ? `${overview.totals.playbackTrend >= 0 ? '+' : ''}${overview.totals.playbackTrend}% vs. periodo anterior` : 'sin comparación'}`}
                    actions={
                        <div className="flex items-center gap-3 text-[11px] text-muted">
                            <span className="flex items-center gap-1.5">
                                <span className="size-2 rounded-full bg-accent" /> Reproducciones
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className="size-2 rounded-full bg-positive" /> Completadas
                            </span>
                        </div>
                    }
                >
                    {overview.series.length === 0 ? (
                        <EmptyState icon={BarChart3} title="Sin datos en el periodo" />
                    ) : (
                        <ResponsiveContainer width="100%" height={280}>
                            <ComposedChart data={overview.series} margin={{ top: 8, right: 8, left: -18, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="playbackFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="#ffc83d" stopOpacity={0.55} />
                                        <stop offset="100%" stopColor="#ffc83d" stopOpacity={0.04} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid stroke="#1b3042" vertical={false} />
                                <XAxis
                                    dataKey="label"
                                    tick={{ fill: '#5f7488', fontSize: 11 }}
                                    axisLine={false}
                                    tickLine={false}
                                    minTickGap={16}
                                />
                                <YAxis
                                    tick={{ fill: '#5f7488', fontSize: 11 }}
                                    axisLine={false}
                                    tickLine={false}
                                    tickFormatter={(value) => formatCompact(value as number)}
                                />
                                <Tooltip
                                    contentStyle={{
                                        background: '#0d1c2b',
                                        border: '1px solid #1b3042',
                                        borderRadius: 10,
                                        fontSize: 12,
                                    }}
                                    labelStyle={{ color: '#f5f7fa' }}
                                    formatter={(value: number, name: string) => [formatNumber(value), name]}
                                />
                                <Area
                                    type="monotone"
                                    dataKey="playbacks"
                                    name="Reproducciones"
                                    stroke="#ffc83d"
                                    strokeWidth={2}
                                    fill="url(#playbackFill)"
                                />
                                <Bar
                                    dataKey="completed"
                                    name="Completadas"
                                    fill="#35d07f"
                                    opacity={0.35}
                                    radius={[3, 3, 0, 0]}
                                    barSize={10}
                                />
                            </ComposedChart>
                        </ResponsiveContainer>
                    )}
                </ChartCard>

                <Card className="xl:col-span-4">
                    <CardHeader>
                        <CardTitle>Estado de dispositivos</CardTitle>
                        <button
                            type="button"
                            onClick={() => goToDevices()}
                            className="text-xs text-accent hover:underline"
                        >
                            Ver todas
                        </button>
                    </CardHeader>
                    <CardContent>
                        <div className="relative mx-auto h-44 w-44">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie
                                        data={health}
                                        dataKey="value"
                                        nameKey="label"
                                        innerRadius={58}
                                        outerRadius={78}
                                        paddingAngle={3}
                                        stroke="none"
                                        onClick={(entry) => goToDevices((entry as { key: string }).key)}
                                        className="cursor-pointer"
                                    >
                                        {health.map((item) => (
                                            <Cell key={item.key} fill={healthColors[item.key]} />
                                        ))}
                                    </Pie>
                                </PieChart>
                            </ResponsiveContainer>
                            <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                <p className="metric text-3xl font-semibold text-fg">
                                    {formatNumber(overview.deviceHealth.total)}
                                </p>
                                <p className="text-[11px] text-muted">Pantallas</p>
                            </div>
                        </div>

                        <div className="mt-4 space-y-2">
                            {health.map((item) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    onClick={() => goToDevices(item.key)}
                                    className="flex w-full items-center gap-2 rounded-control px-2 py-1.5 text-left text-xs transition-colors hover:bg-surface"
                                >
                                    <span
                                        className="size-2 rounded-full"
                                        style={{ background: healthColors[item.key] }}
                                    />
                                    <span className="flex-1 text-muted">{item.label}</span>
                                    <span className="metric text-fg">{formatNumber(item.value)}</span>
                                    <span className="metric text-faint">
                                        {Math.round((item.value / healthTotal) * 100)}%
                                    </span>
                                </button>
                            ))}
                        </div>

                        <div className="mt-4 flex items-center gap-2 rounded-control border border-positive/20 bg-positive/10 px-3 py-2 text-xs text-positive">
                            <Wifi className="size-3.5" />
                            {onlinePercent}% de la red reportando ahora
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-12">
                <Card className="xl:col-span-7">
                    <CardHeader>
                        <div>
                            <CardTitle>Distribución de la red</CardTitle>
                            <p className="mt-0.5 text-xs text-muted">
                                {overview.cities.length} ciudades con pantallas activas
                            </p>
                        </div>
                        <span className="rounded-full border border-line px-3 py-1 text-[11px] text-muted">
                            {formatNumber(overview.cityCoverage)} ciudades con cobertura
                        </span>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {overview.cities.length === 0 ? (
                            <EmptyState icon={Building2} title="Sin ciudades registradas" />
                        ) : (
                            overview.cities.map((city) => {
                                const max = Math.max(...overview.cities.map((c) => c.screens), 1);
                                return (
                                    <div key={city.city} className="flex items-center gap-3">
                                        <span className="w-24 shrink-0 truncate text-xs text-fg">{city.city}</span>
                                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-elevated">
                                            <div
                                                className="h-full rounded-full bg-accent/80"
                                                style={{ width: `${(city.screens / max) * 100}%` }}
                                            />
                                        </div>
                                        <span className="metric w-8 text-right text-xs text-fg">{city.screens}</span>
                                        <span className="metric w-10 text-right text-[11px] text-faint">
                                            {city.online} en línea
                                        </span>
                                    </div>
                                );
                            })
                        )}
                    </CardContent>
                </Card>

                <Card className="xl:col-span-5">
                    <CardHeader>
                        <div>
                            <CardTitle>Vista previa de pantalla</CardTitle>
                            <p className="mt-0.5 text-xs text-muted">
                                {screenPreview?.playback ? 'Último estado recibido de la TV'
                                    : screenPreview ? 'Muestra del contenido y diseño confirmados por la TV'
                                    : 'Selecciona una pantalla para consultar su estado'}
                            </p>
                        </div>
                        <span className="text-xs text-muted">
                            {screenPreview?.playback ? 'Reporte de la TV'
                                : screenPreview ? 'Vista aproximada' : 'Sin pantalla'}
                        </span>
                    </CardHeader>
                    <CardContent>
                        <label className="mb-3 block text-xs text-muted">
                            Pantalla
                            <select className="mt-1 w-full rounded-control border border-line bg-surface p-2 text-fg"
                                value={screenPreview?.device.id ?? ''}
                                onChange={event => router.get('/admin/dashboard', {
                                    from: overview.range.from, to: overview.range.to, device: event.target.value,
                                }, { only: ['screenPreview'], preserveState: true, preserveScroll: true })}>
                                {previewDevices.map(device => <option key={device.id} value={device.id}>{device.name}</option>)}
                            </select>
                        </label>
                        <ScreenPreview preview={screenPreview} />
                    </CardContent>
                </Card>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-12">
                <Card className="xl:col-span-8">
                    <CardHeader>
                        <CardTitle>Rendimiento de campañas</CardTitle>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/admin/campaigns">
                                Ver todas
                                <ArrowRight className="size-3.5" />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="px-0 pb-0 sm:px-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead>
                                    <tr className="border-y border-line text-[11px] uppercase tracking-wider text-faint">
                                        <th className="px-5 py-2.5 text-left font-semibold">Campaña</th>
                                        <th className="px-3 py-2.5 text-left font-semibold">Anunciante</th>
                                        <th className="px-3 py-2.5 text-left font-semibold">Estado</th>
                                        <th className="px-3 py-2.5 text-right font-semibold">Pantallas</th>
                                        <th className="px-3 py-2.5 text-right font-semibold">Reproducciones</th>
                                        <th className="px-5 py-2.5 text-right font-semibold">Finalización</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {overview.campaignPerformance.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="px-5 py-10">
                                                <EmptyState icon={Megaphone} title="Sin campañas con reproducciones" />
                                            </td>
                                        </tr>
                                    ) : (
                                        overview.campaignPerformance.map((campaign) => (
                                            <tr
                                                key={campaign.id}
                                                onClick={() => router.visit(`/admin/campaigns/${campaign.id}`)}
                                                className="cursor-pointer border-b border-line/70 transition-colors last:border-0 hover:bg-surface/60"
                                            >
                                                <td className="px-5 py-3 font-medium text-fg">{campaign.name}</td>
                                                <td className="px-3 py-3 text-muted">{campaign.advertiser?.name ?? '—'}</td>
                                                <td className="px-3 py-3">
                                                    <StatusBadge value={campaign.status} />
                                                </td>
                                                <td className="metric px-3 py-3 text-right text-fg">
                                                    {campaign.target_screen_count}
                                                </td>
                                                <td className="metric px-3 py-3 text-right text-fg">
                                                    {formatNumber(campaign.playbacks_count)}
                                                </td>
                                                <td className="px-5 py-3">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <div className="h-1.5 w-16 overflow-hidden rounded-full bg-elevated">
                                                            <div
                                                                className="h-full rounded-full bg-positive"
                                                                style={{ width: `${Math.min(100, campaign.completion_rate)}%` }}
                                                            />
                                                        </div>
                                                        <span className="metric w-10 text-right text-xs text-muted">
                                                            {campaign.completion_rate}%
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card className="xl:col-span-4">
                    <CardHeader>
                        <CardTitle>Actividad reciente</CardTitle>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/admin/audit">
                                Ver todo
                                <ArrowRight className="size-3.5" />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {overview.recentActivity.alerts.map((alert) => (
                            <div key={`alert-${alert.id}`} className="flex gap-3">
                                <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-danger/12 text-danger">
                                    <TriangleAlert className="size-3.5" />
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-xs font-medium text-fg">{alert.title}</p>
                                    <p className="truncate text-[11px] text-muted">{alert.message}</p>
                                    <p className="text-[10px] text-faint">{formatRelative(alert.triggered_at)}</p>
                                </div>
                            </div>
                        ))}

                        {overview.recentActivity.logs.map((log) => (
                            <div key={`log-${log.id}`} className="flex gap-3">
                                <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-info/12 text-info">
                                    <ShieldCheck className="size-3.5" />
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-xs font-medium text-fg">{log.action}</p>
                                    <p className="truncate text-[11px] text-muted">
                                        {log.user}
                                        {log.entity_type ? ` · ${log.entity_type} #${log.entity_id}` : ''}
                                    </p>
                                    <p className="text-[10px] text-faint">{formatRelative(log.created_at)}</p>
                                </div>
                            </div>
                        ))}

                        {overview.recentActivity.logs.length === 0 && overview.recentActivity.alerts.length === 0 ? (
                            <EmptyState icon={RefreshCw} title="Sin actividad reciente" />
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
