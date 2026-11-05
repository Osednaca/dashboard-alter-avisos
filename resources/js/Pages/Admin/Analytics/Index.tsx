import { Head, Link, router } from '@inertiajs/react';
import { Activity, BarChart3, Clock, Download, MonitorPlay, Target, TrendingUp, Users } from 'lucide-react';
import {
    Area,
    AreaChart,
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
import { Pagination } from '@/Components/app/Pagination';
import { StatCard } from '@/Components/app/StatCard';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { Option, Paginated, SeriesPoint } from '@/Types';
import { formatCompact, formatNumber } from '@/Utils/format';

interface CampaignRow {
    id: number;
    name: string;
    advertiser: string;
    status: string;
    playbacks: number;
    completed: number;
    failures: number;
    completion_rate: number;
}

interface Options {
    campaigns: Array<{ id: number; name: string }>;
    advertisers: Array<{ id: number; name: string }>;
    businesses: Array<{ id: number; name: string }>;
    cities: string[];
    devices: Array<{ id: number; name: string }>;
    categories: Option[];
}

export default function AnalyticsIndex({
    range,
    filters,
    metrics,
    series,
    breakdowns,
    campaigns,
    options,
}: {
    range: { from: string; to: string };
    filters: Record<string, string | undefined>;
    metrics: {
        playbacks: number;
        completed: number;
        completionRate: number;
        failures: number;
        duration: number;
        screens: number;
        businesses: number;
        uptimeHours: number;
    };
    series: SeriesPoint[];
    breakdowns: { cities: Array<{ label: string; playbacks: number; devices: number }>; categories: Array<{ label: string; playbacks: number }> };
    campaigns: Paginated<CampaignRow>;
    options: Options;
}) {
    const applyFilter = (patch: Record<string, string>) => {
        const next: Record<string, string> = { from: range.from, to: range.to, ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key]) delete next[key];
        });
        router.get('/admin/analytics', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const exportUrl = `/admin/analytics/export?${new URLSearchParams({ from: range.from, to: range.to, ...(filters as Record<string, string>) }).toString()}`;

    const columns: Array<Column<CampaignRow>> = [
        {
            key: 'name',
            header: 'Campaña',
            cell: (row) => (
                <Link href={`/admin/campaigns/${row.id}`} className="font-medium text-fg hover:text-accent">
                    {row.name}
                </Link>
            ),
        },
        { key: 'advertiser', header: 'Anunciante', cell: (row) => <span className="text-muted">{row.advertiser}</span> },
        { key: 'status', header: 'Estado', cell: (row) => <span className="text-muted">{row.status}</span> },
        { key: 'playbacks', header: 'Reproducciones', cell: (row) => <span className="metric text-fg">{formatNumber(row.playbacks)}</span> },
        { key: 'completed', header: 'Completadas', cell: (row) => <span className="metric text-muted">{formatNumber(row.completed)}</span> },
        { key: 'failures', header: 'Fallos', cell: (row) => <span className="metric text-danger">{formatNumber(row.failures)}</span> },
        {
            key: 'rate',
            header: 'Finalización',
            cell: (row) => (
                <div className="flex items-center justify-end gap-2">
                    <div className="h-1.5 w-16 overflow-hidden rounded-full bg-elevated">
                        <div className="h-full rounded-full bg-positive" style={{ width: `${Math.min(100, row.completion_rate)}%` }} />
                    </div>
                    <span className="metric w-10 text-right text-xs text-muted">{row.completion_rate}%</span>
                </div>
            ),
        },
    ];

    return (
        <AdminLayout>
            <Head title="Analíticas" />

            <PageHeader
                eyebrow="Datos"
                title="Analíticas"
                description="Actividad de la red basada en proof of play y tablas agregadas diarias."
                actions={
                    <>
                        <DateRangePicker from={range.from} to={range.to} routeName="analytics" extra={filters as Record<string, string>} />
                        <Button variant="secondary" size="sm" asChild>
                            <a href={exportUrl}>
                                <Download className="size-4" />
                                Exportar CSV
                            </a>
                        </Button>
                    </>
                }
            />

            <div className="mt-6 flex flex-wrap gap-2">
                <Select value={filters.campaign_id ?? 'all'} onValueChange={(value) => applyFilter({ campaign_id: value === 'all' ? '' : value })}>
                    <SelectTrigger className="w-full sm:w-48">
                        <SelectValue placeholder="Campaña" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas las campañas</SelectItem>
                        {options.campaigns.map((campaign) => (
                            <SelectItem key={campaign.id} value={String(campaign.id)}>
                                {campaign.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select value={filters.advertiser_id ?? 'all'} onValueChange={(value) => applyFilter({ advertiser_id: value === 'all' ? '' : value })}>
                    <SelectTrigger className="w-full sm:w-48">
                        <SelectValue placeholder="Anunciante" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos los anunciantes</SelectItem>
                        {options.advertisers.map((advertiser) => (
                            <SelectItem key={advertiser.id} value={String(advertiser.id)}>
                                {advertiser.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
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
            </div>

            <div className="mt-4 grid grid-cols-2 gap-4 xl:grid-cols-4">
                <StatCard label="Reproducciones" value={metrics.playbacks} icon={Activity} />
                <StatCard label="Pantallas únicas" value={metrics.screens} icon={MonitorPlay} delay={80} />
                <StatCard label="Negocios únicos" value={metrics.businesses} icon={Users} delay={160} />
                <StatCard label="Tasa de finalización" value={`${metrics.completionRate}%`} icon={TrendingUp} delay={240} />
            </div>
            <div className="mt-4 grid grid-cols-2 gap-4 xl:grid-cols-4">
                <StatCard label="Fallos" value={metrics.failures} icon={Target} />
                <StatCard label="Tiempo reproducido" value={`${metrics.uptimeHours} h`} icon={Clock} delay={80} />
                <StatCard label="Duración total" value={`${Math.round(metrics.duration / 3600)} h`} icon={Clock} delay={160} />
                <StatCard label="Cobertura" value={options.cities.length} icon={BarChart3} delay={240} />
            </div>

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle>Reproducciones por día</CardTitle>
                </CardHeader>
                <CardContent>
                    {series.length === 0 ? (
                        <EmptyState icon={BarChart3} title="Sin datos en el periodo" />
                    ) : (
                        <ResponsiveContainer width="100%" height={300}>
                            <AreaChart data={series} margin={{ top: 8, right: 8, left: -18, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="analyticsFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="#ffc83d" stopOpacity={0.5} />
                                        <stop offset="100%" stopColor="#ffc83d" stopOpacity={0.03} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid stroke="#1b3042" vertical={false} />
                                <XAxis dataKey="label" tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} minTickGap={24} />
                                <YAxis tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} tickFormatter={(v) => formatCompact(v as number)} />
                                <Tooltip contentStyle={{ background: '#0d1c2b', border: '1px solid #1b3042', borderRadius: 10, fontSize: 12 }} formatter={(value: number, name: string) => [formatNumber(value), name]} />
                                <Area type="monotone" dataKey="playbacks" name="Reproducciones" stroke="#ffc83d" strokeWidth={2} fill="url(#analyticsFill)" />
                            </AreaChart>
                        </ResponsiveContainer>
                    )}
                </CardContent>
            </Card>

            <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Reproducciones por ciudad</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {breakdowns.cities.length === 0 ? (
                            <EmptyState icon={BarChart3} title="Sin datos" />
                        ) : (
                            <ResponsiveContainer width="100%" height={260}>
                                <BarChart data={breakdowns.cities} layout="vertical" margin={{ left: 24, right: 16 }}>
                                    <CartesianGrid stroke="#1b3042" horizontal={false} />
                                    <XAxis type="number" tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} tickFormatter={(v) => formatCompact(v as number)} />
                                    <YAxis type="category" dataKey="label" tick={{ fill: '#8fa2b5', fontSize: 11 }} axisLine={false} tickLine={false} width={90} />
                                    <Tooltip contentStyle={{ background: '#0d1c2b', border: '1px solid #1b3042', borderRadius: 10, fontSize: 12 }} cursor={{ fill: '#1b3042', opacity: 0.3 }} />
                                    <Bar dataKey="playbacks" name="Reproducciones" fill="#ffc83d" radius={[0, 4, 4, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Reproducciones por categoría</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {breakdowns.categories.length === 0 ? (
                            <EmptyState icon={BarChart3} title="Sin datos" />
                        ) : (
                            <ResponsiveContainer width="100%" height={260}>
                                <BarChart data={breakdowns.categories} margin={{ top: 8, right: 8, left: -18, bottom: 0 }}>
                                    <CartesianGrid stroke="#1b3042" vertical={false} />
                                    <XAxis dataKey="label" tick={{ fill: '#5f7488', fontSize: 10 }} axisLine={false} tickLine={false} />
                                    <YAxis tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} tickFormatter={(v) => formatCompact(v as number)} />
                                    <Tooltip contentStyle={{ background: '#0d1c2b', border: '1px solid #1b3042', borderRadius: 10, fontSize: 12 }} cursor={{ fill: '#1b3042', opacity: 0.3 }} />
                                    <Bar dataKey="playbacks" name="Reproducciones" fill="#4da3ff" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle>Rendimiento por campaña</CardTitle>
                </CardHeader>
                <CardContent>
                    <DataTable columns={columns} rows={campaigns.data} keyExtractor={(row) => row.id} empty={<EmptyState icon={BarChart3} title="Sin datos" />} />
                    <Pagination paginator={campaigns} />
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
