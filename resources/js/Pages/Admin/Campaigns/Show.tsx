import { Head, Link, router } from '@inertiajs/react';
import { Archive, Bandage, Calendar, Pause, Play, Target, TrendingUp } from 'lucide-react';
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
import { MediaThumbnail } from '@/Components/app/MediaThumbnail';
import { PageHeader } from '@/Components/app/PageHeader';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { usePermissions } from '@/Hooks/usePermissions';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { CampaignEntity, DeviceEntity, EnumValue, MediaEntity } from '@/Types';
import { formatCompact, formatDuration, formatNumber } from '@/Utils/format';

interface CreativeRow {
    id: number;
    duration: number;
    weight: number;
    status: EnumValue;
    media: MediaEntity | null;
}

interface TargetRow {
    id: number;
    target_type: EnumValue;
    target_id: number | null;
    target_value: string | null;
    is_exclusion: boolean;
}

export default function CampaignShow({
    campaign,
    creatives,
    targets,
    targetSummary,
    targetDevices,
    analytics,
}: {
    campaign: CampaignEntity;
    creatives: CreativeRow[];
    targets: TargetRow[];
    targetSummary: { screens: number; businesses: number; locations: number; cities: number };
    targetDevices: DeviceEntity[];
    analytics: { series: Array<{ label: string; playbacks: number; completed: number; failures: number }>; totals: Record<string, number> };
}) {
    const { can } = usePermissions();

    const deviceColumns: Array<Column<DeviceEntity>> = [
        { key: 'name', header: 'Pantalla', cell: (row) => <Link href={`/admin/devices/${row.id}`} className="font-medium text-fg hover:text-accent">{row.name}</Link> },
        { key: 'business', header: 'Negocio', cell: (row) => <span className="text-muted">{row.business?.name ?? '—'}</span> },
        { key: 'city', header: 'Ciudad', cell: (row) => <span className="text-muted">{row.location?.city ?? '—'}</span> },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
    ];

    return (
        <AdminLayout>
            <Head title={campaign.name} />

            <PageHeader
                eyebrow={campaign.advertiser?.name ?? 'Campaña'}
                title={campaign.name}
                description={campaign.description ?? campaign.schedule_label}
                actions={
                    <>
                        <StatusBadge value={campaign.status} />
                        {can('campaigns.edit') ? (
                            <Button variant="secondary" size="sm" asChild>
                                <Link href={`/admin/campaigns/${campaign.id}/edit`}>Editar</Link>
                            </Button>
                        ) : null}
                        {can('campaigns.publish') && !['completed', 'archived'].includes(campaign.status.value) ? (
                            campaign.status.value === 'active' ? (
                                <Button variant="outline" size="sm" onClick={() => router.post(`/admin/campaigns/${campaign.id}/pause`, {}, { preserveScroll: true })}>
                                    <Pause className="size-4" />
                                    Pausar
                                </Button>
                            ) : (
                                <Button variant="primary" size="sm" onClick={() => router.post(`/admin/campaigns/${campaign.id}/${campaign.status.value === 'paused' ? 'resume' : 'publish'}`, {}, { preserveScroll: true })}>
                                    <Play className="size-4" />
                                    {campaign.status.value === 'paused' ? 'Reactivar' : 'Publicar'}
                                </Button>
                            )
                        ) : null}
                        {can('campaigns.publish') ? (
                            <Button variant="ghost" size="sm" onClick={() => router.post(`/admin/campaigns/${campaign.id}/archive`, {}, { preserveScroll: true })}>
                                <Archive className="size-4" />
                                Archivar
                            </Button>
                        ) : null}
                    </>
                }
            />

            <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Card>
                    <CardContent className="pt-4">
                        <p className="flex items-center gap-1.5 text-xs text-muted">
                            <Target className="size-3.5 text-faint" /> Pantallas
                        </p>
                        <p className="metric mt-1 text-2xl text-fg">{campaign.target_screen_count}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-4">
                        <p className="text-xs text-muted">Reproducciones</p>
                        <p className="metric mt-1 text-2xl text-fg">{formatCompact(analytics.totals.playbacks ?? 0)}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-4">
                        <p className="flex items-center gap-1.5 text-xs text-muted">
                            <TrendingUp className="size-3.5 text-faint" /> Finalización
                        </p>
                        <p className="metric mt-1 text-2xl text-positive">{campaign.completion_rate}%</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-4">
                        <p className="flex items-center gap-1.5 text-xs text-muted">
                            <Bandage className="size-3.5 text-faint" /> Fallos
                        </p>
                        <p className="metric mt-1 text-2xl text-danger">{formatNumber(analytics.totals.failures ?? 0)}</p>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
                <Card className="xl:col-span-2">
                    <CardHeader>
                        <CardTitle>Entrega de la campaña</CardTitle>
                        <span className="text-xs text-muted">{formatDuration(analytics.totals.duration ?? 0)} reproducidos</span>
                    </CardHeader>
                    <CardContent>
                        {analytics.series.length === 0 ? (
                            <EmptyState icon={TrendingUp} title="Sin reproducciones aún" />
                        ) : (
                            <ResponsiveContainer width="100%" height={260}>
                                <ComposedChart data={analytics.series} margin={{ top: 8, right: 8, left: -18, bottom: 0 }}>
                                    <CartesianGrid stroke="#1b3042" vertical={false} />
                                    <XAxis dataKey="label" tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} minTickGap={20} />
                                    <YAxis tick={{ fill: '#5f7488', fontSize: 11 }} axisLine={false} tickLine={false} tickFormatter={(v) => formatCompact(v as number)} />
                                    <Tooltip contentStyle={{ background: '#0d1c2b', border: '1px solid #1b3042', borderRadius: 10, fontSize: 12 }} formatter={(value: number, name: string) => [formatNumber(value), name]} />
                                    <Bar dataKey="playbacks" name="Reproducciones" fill="#ffc83d" radius={[3, 3, 0, 0]} />
                                    <Bar dataKey="completed" name="Completadas" fill="#35d07f" radius={[3, 3, 0, 0]} />
                                </ComposedChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Programación y segmentación</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div className="flex items-start gap-2 text-muted">
                            <Calendar className="mt-0.5 size-4 text-faint" />
                            <div>
                                <p className="text-fg">{campaign.starts_at} → {campaign.ends_at}</p>
                                <p className="text-xs text-faint">
                                    {campaign.daily_start_time?.slice(0, 5) ?? '00:00'}–{campaign.daily_end_time?.slice(0, 5) ?? '23:59'} · prioridad {campaign.priority}
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            {[
                                { label: 'Pantallas', value: targetSummary.screens },
                                { label: 'Negocios', value: targetSummary.businesses },
                                { label: 'Ubicaciones', value: targetSummary.locations },
                                { label: 'Ciudades', value: targetSummary.cities },
                            ].map((item) => (
                                <div key={item.label} className="rounded-control border border-line bg-surface px-3 py-2">
                                    <p className="text-[11px] text-faint">{item.label}</p>
                                    <p className="metric text-sm text-fg">{item.value}</p>
                                </div>
                            ))}
                        </div>

                        <div className="space-y-1.5">
                            <p className="text-xs text-muted">Reglas de segmentación</p>
                            <div className="flex flex-wrap gap-1.5">
                                {targets.length === 0 ? (
                                    <span className="text-xs text-faint">Sin reglas</span>
                                ) : (
                                    targets.map((target) => (
                                        <Badge key={target.id} tone={target.is_exclusion ? 'danger' : 'info'}>
                                            {target.is_exclusion ? 'Excluye ' : ''}
                                            {target.target_type.label}
                                            {target.target_value ? `: ${target.target_value}` : ''}
                                        </Badge>
                                    ))
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Creatividades</CardTitle>
                        <span className="text-xs text-muted">{creatives.length} piezas</span>
                    </CardHeader>
                    <CardContent>
                        {creatives.length === 0 ? (
                            <EmptyState icon={Target} title="Sin creatividades asignadas" />
                        ) : (
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {creatives.map((creative) => (
                                    <div key={creative.id} className="space-y-2">
                                        <MediaThumbnail media={creative.media} />
                                        <p className="truncate text-xs text-fg">{creative.media?.filename ?? '—'}</p>
                                        <p className="text-[11px] text-faint">
                                            {creative.duration}s · peso {creative.weight}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Pantallas objetivo</CardTitle>
                        <span className="text-xs text-muted">Muestra de {targetDevices.length}</span>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={deviceColumns}
                            rows={targetDevices}
                            keyExtractor={(row) => row.id}
                            empty={<EmptyState icon={Target} title="La segmentación no coincide con pantallas" />}
                        />
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
