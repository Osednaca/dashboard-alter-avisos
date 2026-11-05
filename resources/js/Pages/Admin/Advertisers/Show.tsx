import { Head, Link } from '@inertiajs/react';
import { BarChart3, Building2, Mail, Phone, PlayCircle } from 'lucide-react';
import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { MediaThumbnail } from '@/Components/app/MediaThumbnail';
import { PageHeader } from '@/Components/app/PageHeader';
import { UploadDropzone } from '@/Components/app/UploadDropzone';
import { usePermissions } from '@/Hooks/usePermissions';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { AdvertiserEntity, CampaignEntity, MediaEntity } from '@/Types';
import { formatCompact, formatNumber } from '@/Utils/format';

export default function AdvertiserShow({
    advertiser,
    campaigns,
    creatives,
    analytics,
}: {
    advertiser: AdvertiserEntity;
    campaigns: CampaignEntity[];
    creatives: MediaEntity[];
    analytics: { series: Array<{ date: string; label: string; playbacks: number; completed: number }> };
}) {
    const { can } = usePermissions();
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
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'screens', header: 'Pantallas', cell: (row) => <span className="metric text-fg">{row.target_screen_count}</span> },
        { key: 'playbacks', header: 'Reproducciones', cell: (row) => <span className="metric text-fg">{formatNumber(row.playbacks_count)}</span> },
    ];

    return (
        <AdminLayout>
            <Head title={advertiser.name} />

            <PageHeader
                eyebrow="Anunciante"
                title={advertiser.name}
                description={`${advertiser.active_campaigns_count} campañas activas · ${formatCompact(advertiser.total_playbacks)} reproducciones`}
                actions={<StatusBadge value={advertiser.status} />}
            />

            <Tabs defaultValue="overview" className="mt-6">
                <TabsList>
                    <TabsTrigger value="overview">Resumen</TabsTrigger>
                    <TabsTrigger value="campaigns">Campañas</TabsTrigger>
                    <TabsTrigger value="creatives">Creatividades</TabsTrigger>
                    <TabsTrigger value="analytics">Analíticas</TabsTrigger>
                    <TabsTrigger value="contact">Contacto</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Rendimiento</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {analytics.series.length === 0 ? (
                                <EmptyState icon={BarChart3} title="Sin datos de rendimiento" />
                            ) : (
                                <ResponsiveContainer width="100%" height={240}>
                                    <LineChart data={analytics.series} margin={{ top: 8, right: 8, left: -18, bottom: 0 }}>
                                        <CartesianGrid stroke="#1b3042" vertical={false} />
                                        <XAxis dataKey="label" tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} minTickGap={20} />
                                        <YAxis tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} tickFormatter={(v) => formatCompact(v as number)} />
                                        <Tooltip contentStyle={{ background: '#0d1c2b', border: '1px solid #1b3042', borderRadius: 10, fontSize: 12 }} formatter={(value: number, name: string) => [formatNumber(value), name]} />
                                        <Line type="monotone" dataKey="playbacks" name="Reproducciones" stroke="#ffc83d" strokeWidth={2} dot={false} />
                                    </LineChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Contacto</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 break-words text-sm">
                            <p className="flex min-w-0 items-center gap-2 break-all text-muted">
                                <Building2 className="size-4 shrink-0 text-faint" /> {advertiser.contact_name ?? '—'}
                            </p>
                            <p className="flex min-w-0 items-center gap-2 break-all text-muted">
                                <Mail className="size-4 shrink-0 text-faint" /> {advertiser.contact_email ?? '—'}
                            </p>
                            <p className="flex min-w-0 items-center gap-2 break-all text-muted">
                                <Phone className="size-4 shrink-0 text-faint" /> {advertiser.contact_phone ?? '—'}
                            </p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="campaigns" className="mt-4">
                    <Card>
                        <CardContent className="pt-4">
                            <DataTable columns={campaignColumns} rows={campaigns} keyExtractor={(row) => row.id} empty={<EmptyState icon={Building2} title="Sin campañas" />} />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="creatives" className="mt-4 space-y-4">
                    {can('creatives.manage') && <UploadDropzone action="/admin/creatives" metadata={{ advertiser_id: String(advertiser.id) }} />}
                    {creatives.length === 0 ? (
                        <EmptyState icon={PlayCircle} title="Sin creatividades" />
                    ) : (
                        <div className="grid grid-cols-1 gap-4 min-[400px]:grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
                            {creatives.map((creative) => (
                                <Card key={creative.id}>
                                    <CardContent className="space-y-2 pt-4">
                                        <MediaThumbnail media={creative} />
                                        <p className="truncate text-xs text-fg">{creative.filename}</p>
                                        <p className="text-[11px] text-faint">
                                            {creative.resolution ?? '—'} · {creative.human_filesize}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </TabsContent>

                <TabsContent value="analytics" className="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Reproducciones por día</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {analytics.series.length === 0 ? (
                                <EmptyState icon={BarChart3} title="Sin datos" />
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <LineChart data={analytics.series} margin={{ top: 8, right: 8, left: -18, bottom: 0 }}>
                                        <CartesianGrid stroke="#1b3042" vertical={false} />
                                        <XAxis dataKey="label" tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} minTickGap={20} />
                                        <YAxis tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} tickFormatter={(v) => formatCompact(v as number)} />
                                        <Tooltip contentStyle={{ background: '#0d1c2b', border: '1px solid #1b3042', borderRadius: 10, fontSize: 12 }} />
                                        <Line type="monotone" dataKey="playbacks" name="Reproducciones" stroke="#ffc83d" strokeWidth={2} dot={false} />
                                        <Line type="monotone" dataKey="completed" name="Completadas" stroke="#35d07f" strokeWidth={2} dot={false} />
                                    </LineChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="contact" className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Contacto comercial</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 break-words text-sm">
                            <p className="text-muted">Nombre: <span className="text-fg">{advertiser.contact_name ?? '—'}</span></p>
                            <p className="text-muted">Correo: <span className="text-fg">{advertiser.contact_email ?? '—'}</span></p>
                            <p className="text-muted">Teléfono: <span className="text-fg">{advertiser.contact_phone ?? '—'}</span></p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Facturación</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 break-words text-sm">
                            <p className="text-muted">Razón social: <span className="text-fg">{advertiser.billing_name ?? '—'}</span></p>
                            <p className="text-muted">NIT: <span className="text-fg">{advertiser.billing_tax_id ?? '—'}</span></p>
                            <p className="text-muted">Correo: <span className="text-fg">{advertiser.billing_email ?? '—'}</span></p>
                            <p className="text-muted">Dirección: <span className="text-fg">{advertiser.billing_address ?? '—'}</span></p>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </AdminLayout>
    );
}
