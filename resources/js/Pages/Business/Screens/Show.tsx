import { Head, Link, router, usePoll } from '@inertiajs/react';
import { ArrowLeft, HardDrive, LayoutTemplate, ListVideo, RefreshCw, Wifi, WifiOff } from 'lucide-react';
import { BusinessScreenPreview, type BusinessPreviewData } from '@/Components/app/BusinessScreenPreview';
import { PageHeader } from '@/Components/app/PageHeader';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Progress } from '@/Components/ui/progress';
import { usePermissions } from '@/Hooks/usePermissions';
import { BusinessLayout } from '@/Layouts/BusinessLayout';
import type { DeviceEntity } from '@/Types';
import { formatBytes, formatDateTime, formatRelative } from '@/Utils/format';

export default function ScreenShow({
    device,
    preview,
}: {
    device: DeviceEntity;
    preview: BusinessPreviewData | null;
}) {
    usePoll(3000, { only: ['preview', 'device'] });
    const { can } = usePermissions();

    return (
        <BusinessLayout>
            <Head title={device.name} />

            <PageHeader
                eyebrow="Pantalla"
                title={device.name}
                description={`${device.location?.name ?? 'Sin ubicación'} · Layout ${device.current_layout?.ratio ?? '—'}`}
                actions={
                    <>
                        <StatusBadge value={device.status} />
                        {can('business.devices.sync') ? (
                            <Button
                                variant="primary"
                                size="sm"
                                onClick={() => router.post(`/business/screens/${device.id}/sync`, {}, { preserveScroll: true })}
                            >
                                <RefreshCw className="size-4" />
                                Sincronizar ahora
                            </Button>
                        ) : null}
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/business/screens">
                                <ArrowLeft className="size-4" />
                                Volver
                            </Link>
                        </Button>
                    </>
                }
            />

            <div className="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-12">
                <Card className="xl:col-span-7">
                    <CardHeader>
                        <CardTitle>Contenido actual</CardTitle>
                        {device.is_online ? (
                            <span className="inline-flex items-center gap-1.5 text-xs text-positive">
                                <span className="size-1.5 animate-pulse rounded-full bg-positive" /> TV conectada
                            </span>
                        ) : (
                            <span className="inline-flex items-center gap-1.5 text-xs text-danger">
                                <WifiOff className="size-3" /> Sin conexión
                            </span>
                        )}
                    </CardHeader>
                    <CardContent>
                        <BusinessScreenPreview preview={preview} />
                    </CardContent>
                </Card>

                <div className="space-y-4 xl:col-span-5">
                    <div className="grid grid-cols-2 gap-4">
                        <Card>
                            <CardContent className="pt-4">
                                <p className="flex items-center gap-1.5 text-xs text-muted">
                                    <LayoutTemplate className="size-3.5 text-faint" /> Layout
                                </p>
                                <p className="mt-1 text-sm text-fg">{device.current_layout?.name ?? '—'}</p>
                                <p className="metric text-[11px] text-faint">
                                    Negocio {preview?.layout?.business_percentage ?? '—'}% · Publicidad{' '}
                                    {preview?.layout?.advertising_percentage ?? '—'}%
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-4">
                                <p className="flex items-center gap-1.5 text-xs text-muted">
                                    <ListVideo className="size-3.5 text-faint" /> Playlist
                                </p>
                                <p className="mt-1 truncate text-sm text-fg">{device.current_playlist?.name ?? '—'}</p>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Estado del dispositivo</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <Row
                                label="Conectividad"
                                value={
                                    <span className={device.is_online ? 'text-positive' : 'text-danger'}>
                                        <span className="inline-flex items-center gap-1.5">
                                            {device.is_online ? <Wifi className="size-3.5" /> : <WifiOff className="size-3.5" />}
                                            {device.is_online ? 'En línea' : 'Desconectada'}
                                        </span>
                                    </span>
                                }
                            />
                            <Row label="Última conexión" value={formatRelative(device.last_seen_at)} />
                            <Row label="Última sincronización" value={formatDateTime(device.last_sync_at)} />
                            <Row label="Versión de la app" value={device.app_version ?? '—'} mono />
                            <Row label="Manifiesto" value={device.current_manifest_version ?? '—'} mono />
                            <div>
                                <div className="mb-1 flex items-center justify-between text-xs">
                                    <span className="flex items-center gap-1.5 text-muted">
                                        <HardDrive className="size-3.5 text-faint" /> Almacenamiento
                                    </span>
                                    <span className="metric text-fg">
                                        {formatBytes(device.storage_free)} libres
                                    </span>
                                </div>
                                <Progress
                                    value={device.storage_usage ?? 0}
                                    indicatorClassName={(device.storage_usage ?? 0) > 85 ? 'bg-danger' : 'bg-accent'}
                                />
                            </div>
                            <p className="rounded-control border border-line bg-surface px-3 py-2 text-[11px] leading-relaxed text-faint">
                                El layout y el espacio publicitario los administra la plataforma. Tu contenido ocupa la
                                zona asignada al negocio.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </BusinessLayout>
    );
}

function Row({ label, value, mono = false }: { label: string; value: React.ReactNode; mono?: boolean }) {
    return (
        <div className="flex items-center justify-between gap-4 border-b border-line/60 pb-2 last:border-0">
            <span className="text-xs text-muted">{label}</span>
            <span className={`truncate text-right text-fg ${mono ? 'metric text-xs' : ''}`}>{value}</span>
        </div>
    );
}
