import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    Loader2,
    MonitorPlay,
    RefreshCw,
    TriangleAlert,
    XCircle,
    Zap,
} from 'lucide-react';
import { useEffect } from 'react';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { PageHeader } from '@/Components/app/PageHeader';
import { QuickPlayPreview } from '@/Components/app/QuickPlayPreview';
import { QuickPlayActions } from '@/Components/app/QuickPlayActions';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { BusinessLayout } from '@/Layouts/BusinessLayout';
import type { QuickPlayDeviceEntity, QuickPlayEntity } from '@/Types';
import { getEcho } from '@/Utils/echo';
import { formatDateTime, formatNumber, formatRelative } from '@/Utils/format';

const terminalStatuses = ['completed', 'partial', 'failed'];

export default function QuickPlayShow({
    quickPlay,
    devices,
    portal = 'admin',
}: {
    portal?: 'admin' | 'business';
    quickPlay: QuickPlayEntity;
    devices: QuickPlayDeviceEntity[];
}) {
    const PageLayout = portal === 'business' ? BusinessLayout : AdminLayout;
    const basePath = `/${portal}/quick-play`;
    const isTerminal = terminalStatuses.includes(quickPlay.status.value);

    // Real-time updates over Reverb when available.
    useEffect(() => {
        if (portal === 'business') return;
        const echo = getEcho();
        if (!echo) return;

        const channelName = `quick-plays.${quickPlay.id}`;
        const channel = echo.private(channelName);
        channel.listen('.QuickPlayStatusUpdated', () => {
            router.reload({ only: ['quickPlay', 'devices'] });
        });

        return () => {
            echo.leave(channelName);
        };
    }, [quickPlay.id, portal]);

    // Polling fallback keeps the board accurate even without websockets.
    useEffect(() => {
        if (isTerminal) return;
        const timer = setInterval(() => router.reload({ only: ['quickPlay', 'devices'] }), 6000);
        return () => clearInterval(timer);
    }, [isTerminal]);

    const columns: Array<Column<QuickPlayDeviceEntity>> = [
        {
            key: 'device',
            header: 'Pantalla',
            cell: (row) => (
                <div className="min-w-0">
                    <span className="block truncate font-medium text-fg">{row.device?.name ?? 'Pantalla eliminada'}</span>
                    <span className="block truncate text-xs text-faint">
                        {[row.device?.business, row.device?.city].filter(Boolean).join(' · ') || '—'}
                    </span>
                </div>
            ),
        },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        {
            key: 'error',
            header: 'Detalle',
            cell: (row) =>
                row.error ? (
                    <span className="text-xs text-danger">{row.error}</span>
                ) : row.status.value === 'playing' ? (
                    <span className="text-xs text-accent">En pantalla ahora</span>
                ) : (
                    <span className="text-xs text-faint">—</span>
                ),
        },
        { key: 'sent', header: 'Enviado', cell: (row) => <span className="text-xs text-muted">{formatRelative(row.sent_at)}</span> },
        { key: 'started', header: 'Inició', cell: (row) => <span className="text-xs text-muted">{row.started_at ? formatRelative(row.started_at) : '—'}</span> },
        { key: 'completed', header: 'Finalizó', cell: (row) => <span className="text-xs text-muted">{row.completed_at ? formatRelative(row.completed_at) : '—'}</span> },
    ];

    const inFlight = devices.filter((device) => !['completed', 'failed'].includes(device.status.value)).length;

    return (
        <PageLayout>
            <Head title={`Reproducción inmediata #${quickPlay.id}`} />

            <PageHeader
                eyebrow="Reproducción inmediata"
                title={quickPlay.media?.filename ?? `Envío #${quickPlay.id}`}
                description={`${quickPlay.display_mode.label} · ${quickPlay.scope.label} · ${quickPlay.duration_label}`}
                actions={
                    <>
                        <StatusBadge value={quickPlay.status} />
                        <QuickPlayActions quickPlay={quickPlay} portal={portal} />
                        {!isTerminal ? (
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => router.reload({ only: ['quickPlay', 'devices'] })}
                            >
                                <RefreshCw className="size-4" />
                                Actualizar
                            </Button>
                        ) : null}
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={basePath}>
                                <ArrowLeft className="size-4" />
                                Historial
                            </Link>
                        </Button>
                    </>
                }
            />

            {quickPlay.retry_of_id ? (
                <p className="mt-4 text-sm text-muted">Reintento del envío #{quickPlay.retry_of_id}. Incluye solo las pantallas que fallaron.</p>
            ) : null}

            <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Card>
                    <CardContent className="pt-4">
                        <p className="flex items-center gap-1.5 text-xs text-muted">
                            <MonitorPlay className="size-3.5 text-faint" /> Pantallas
                        </p>
                        <p className="metric mt-1 text-2xl text-fg">{formatNumber(quickPlay.targets_count)}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-4">
                        <p className="flex items-center gap-1.5 text-xs text-muted">
                            <CheckCircle2 className="size-3.5 text-positive" /> Completadas
                        </p>
                        <p className="metric mt-1 text-2xl text-positive">{formatNumber(quickPlay.delivered_count)}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-4">
                        <p className="flex items-center gap-1.5 text-xs text-muted">
                            <Loader2 className="size-3.5 text-info" /> En curso
                        </p>
                        <p className="metric mt-1 text-2xl text-info">{formatNumber(quickPlay.pending_count)}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-4">
                        <p className="flex items-center gap-1.5 text-xs text-muted">
                            <XCircle className="size-3.5 text-danger" /> Fallidas
                        </p>
                        <p className="metric mt-1 text-2xl text-danger">{formatNumber(quickPlay.failed_count)}</p>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-12">
                <Card className="xl:col-span-5">
                    <CardHeader>
                        <CardTitle>Vista previa</CardTitle>
                        {!isTerminal ? (
                            <span className="inline-flex items-center gap-1.5 text-xs text-accent">
                                <span className="size-1.5 animate-pulse rounded-full bg-accent" /> {inFlight} pantallas en proceso
                            </span>
                        ) : null}
                    </CardHeader>
                    <CardContent>
                        <QuickPlayPreview media={quickPlay.media} displayMode={quickPlay.display_mode.value} />
                    </CardContent>
                </Card>

                <Card className="xl:col-span-7">
                    <CardHeader>
                        <CardTitle>Detalles del envío</CardTitle>
                        <span className="inline-flex items-center gap-1.5 text-xs text-faint">
                            <Zap className="size-3" /> Sin campaña · restaura el contenido al finalizar
                        </span>
                    </CardHeader>
                    <CardContent className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Detail label="Modo de visualización" value={quickPlay.display_mode.label} />
                        <Detail label="Destino" value={quickPlay.scope.label} />
                        <Detail label="Duración" value={quickPlay.duration_label} />
                        <Detail label="Tipo de contenido" value={quickPlay.media?.type?.label ?? '—'} />
                        <Detail label="Enviado por" value={quickPlay.user} />
                        <Detail label="Enviado" value={formatDateTime(quickPlay.created_at)} />
                        <Detail label="Expira" value={quickPlay.expires_at ? formatDateTime(quickPlay.expires_at) : '—'} />
                        <Detail label="Contenido" value={quickPlay.media?.filename ?? '—'} />
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle>Entrega por pantalla</CardTitle>
                    <span className="inline-flex items-center gap-1.5 text-xs text-faint">
                        <Clock className="size-3" /> Se actualiza en tiempo real
                    </span>
                </CardHeader>
                <CardContent>
                    <DataTable
                        columns={columns}
                        rows={devices}
                        keyExtractor={(row) => row.id}
                        empty={<EmptyState icon={MonitorPlay} title="Sin pantallas objetivo" />}
                    />
                </CardContent>
            </Card>

            {quickPlay.failed_count > 0 ? (
                <div className="mt-4 flex items-start gap-2 rounded-card border border-danger/25 bg-danger/10 px-4 py-3 text-xs text-danger">
                    <TriangleAlert className="mt-0.5 size-3.5 shrink-0" />
                    {quickPlay.failed_count} pantalla(s) no pudieron completar la reproducción. Revisa el detalle de cada pantalla;
                    {quickPlay.can_retry ? ' después de corregir la causa, pulsa «Reintentar fallidas». Solo se reenviará a esas pantallas.'
                        : quickPlay.retry_id ? ' ya se creó un reintento; puedes consultar su entrega con «Ver reintento».'
                        : ' podrás reintentar cuando finalicen las entregas en curso.'}
                </div>
            ) : null}
        </PageLayout>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-control border border-line bg-surface px-3 py-2">
            <p className="text-[11px] text-faint">{label}</p>
            <p className="truncate text-sm text-fg">{value}</p>
        </div>
    );
}
