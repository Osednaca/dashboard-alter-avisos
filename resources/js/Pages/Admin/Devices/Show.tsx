import { DeviceDiagnostics, describeDeviceError, type DeviceDiagnosticsData } from '@/Components/app/DeviceDiagnostics';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import {
    Clock,
    HardDrive,
    KeyRound,
    MonitorPlay,
    PlayCircle,
    RefreshCw,
    Send,
    Trash2,
    Wifi,
    WifiOff,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/Components/app/ConfirmDialog';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { PageHeader } from '@/Components/app/PageHeader';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Progress } from '@/Components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { usePermissions } from '@/Hooks/usePermissions';
import type { CommandEntity, DeviceEntity, EnumValue } from '@/Types';
import { formatBytes, formatDateTime, formatRelative } from '@/Utils/format';

interface CommandType {
    value: string;
    label: string;
    requires_confirmation: boolean;
}

interface ManifestRow {
    id: number;
    version: string;
    status: EnumValue;
    checksum: string | null;
    generated_at: string | null;
    activated_at: string | null;
}

interface PlaybackRow {
    id: number;
    media: string | null;
    campaign: string | null;
    started_at: string | null;
    duration_played: number;
    completed: boolean;
    error_code: string | null;
}

interface HeartbeatRow {
    diagnostics?: DeviceDiagnosticsData | null;
    recorded_at: string | null;
    available_storage: number | null;
    player_status: string | null;
    network_status: string | null;
}

export default function DeviceShow({
    device,
    commands,
    manifests,
    playback,
    heartbeats,
    failures,
    commandTypes,
    adminPinConfigured,
    canManagePin,
}: {
    device: DeviceEntity;
    commands: CommandEntity[];
    manifests: ManifestRow[];
    playback: PlaybackRow[];
    heartbeats: HeartbeatRow[];
    failures: PlaybackRow[];
    commandTypes: CommandType[];
    adminPinConfigured: boolean;
    canManagePin: boolean;
}) {
    const [pendingCommand, setPendingCommand] = useState<CommandType | null>(null);
    const [confirmingRevoke, setConfirmingRevoke] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const { can } = usePermissions();
    usePoll(15000, { only: ['heartbeats', 'failures', 'commands', 'playback'] });

    const sendCommand = (type: CommandType) => {
        if (type.requires_confirmation) {
            setPendingCommand(type);
            return;
        }
        executeCommand(type);
    };

    const executeCommand = (type: CommandType) => {
        router.post(
            `/admin/devices/${device.id}/commands`,
            { command: type.value, confirm: true },
            { preserveScroll: true, onSuccess: () => toast.success(`Comando «${type.label}» enviado.`) },
        );
    };

    return (
        <AdminLayout>
            <Head title={device.name} />

            <PageHeader
                eyebrow={device.business?.name ?? 'Pantalla'}
                title={device.name}
                description={`${device.location?.name ?? 'Sin ubicación'} · ${device.current_layout?.name ?? 'Sin layout'} ${device.current_layout ? `(${device.current_layout.ratio})` : ''}`}
                actions={
                    <>
                        <StatusBadge value={device.status} />
                        <Button variant="secondary" size="sm" onClick={() => router.post(`/admin/devices/${device.id}/sync`, {}, { preserveScroll: true })}>
                            <RefreshCw className="size-4" />
                            Sincronizar
                        </Button>
                        {can('devices.manage') ? (
                            <Button variant="danger" size="sm" onClick={() => setConfirmingDelete(true)}>
                                <Trash2 className="size-4" />
                                Eliminar
                            </Button>
                        ) : null}
                    </>
                }
            />

            <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardContent className="pt-4">
                        <p className="flex items-center gap-1.5 text-xs text-muted">
                            <Clock className="size-3.5 text-faint" /> Último latido
                        </p>
                        <p className="mt-1 text-sm text-fg">{formatRelative(device.last_seen_at)}</p>
                        <p className="text-[11px] text-faint">{formatDateTime(device.last_seen_at)}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-4">
                        <p className="flex items-center gap-1.5 text-xs text-muted">
                            <HardDrive className="size-3.5 text-faint" /> Almacenamiento
                        </p>
                        <p className="mt-1 text-sm text-fg">{formatBytes(device.storage_free)} libres</p>
                        {device.storage_usage !== null ? (
                            <Progress value={device.storage_usage} className="mt-2" indicatorClassName={device.storage_usage > 85 ? 'bg-danger' : 'bg-accent'} />
                        ) : null}
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-4">
                        <p className="flex items-center gap-1.5 text-xs text-muted">
                            <MonitorPlay className="size-3.5 text-faint" /> Manifiesto
                        </p>
                        <p className="metric mt-1 text-sm text-fg">{device.current_manifest_version ?? '—'}</p>
                        {device.pending_manifest_version ? (
                            <Badge tone="info" className="mt-1">
                                Pendiente {device.pending_manifest_version}
                            </Badge>
                        ) : null}
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-4">
                        <p className="flex items-center gap-1.5 text-xs text-muted">
                            {device.is_online ? <Wifi className="size-3.5 text-positive" /> : <WifiOff className="size-3.5 text-danger" />}
                            Conectividad
                        </p>
                        <p className={`mt-1 text-sm ${device.is_online ? 'text-positive' : 'text-danger'}`}>
                            {device.is_online ? 'En línea' : 'Desconectada'}
                        </p>
                        <p className="metric text-[11px] text-faint">App {device.app_version ?? '—'}</p>
                    </CardContent>
                </Card>
            </div>

            <Tabs defaultValue="overview" className="mt-4">
                <TabsList>
                    <TabsTrigger value="overview">Resumen</TabsTrigger>
                    <TabsTrigger value="commands">Comandos</TabsTrigger>
                    <TabsTrigger value="sync">Sincronización</TabsTrigger>
                    <TabsTrigger value="playback">Reproducciones</TabsTrigger>
                    <TabsTrigger value="logs">Logs</TabsTrigger>
                    <TabsTrigger value="validation">Validación</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Contenido actual</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <Row label="Layout" value={device.current_layout?.name ?? '—'} />
                            <Row label="Lista de reproducción" value={device.current_playlist?.name ?? '—'} />
                            <Row label="Manifiesto activo" value={device.current_manifest_version ?? '—'} />
                            <Row label="Campaña asignada" value="Gestionada por la red publicitaria" />
                            <Row label="Última sincronización" value={formatRelative(device.last_sync_at)} />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Identificación</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <Row label="UUID" value={device.uuid} mono />
                            <Row label="Negocio" value={device.business?.name ?? '—'} />
                            <Row label="Ubicación" value={device.location?.name ?? '—'} />
                            <Row label="Ciudad" value={device.location?.city ?? '—'} />
                        </CardContent>
                    </Card>
                    <Card className="xl:col-span-2">
                        <CardHeader><CardTitle>PIN global de acceso a Configuración</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-sm text-muted">
                                Todas las pantallas de todos los negocios usan el mismo PIN. Los PIN anteriores por pantalla ya no son válidos.{' '}
                                {adminPinConfigured ? 'El PIN global está configurado.' : 'El administrador debe configurar el PIN global para habilitar el acceso.'}
                                {' '}El TV necesita conexión con el servidor para validarlo.
                            </p>
                            {canManagePin ? (
                                <Button asChild variant="outline"><Link href="/admin/devices/global-pin"><KeyRound className="size-4" />Administrar PIN global</Link></Button>
                            ) : <p className="text-xs text-faint">Solicita el PIN al administrador de las pantallas.</p>}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="commands" className="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Enviar comando remoto</CardTitle>
                            <Button variant="ghost" size="sm" onClick={() => setConfirmingRevoke(true)}>
                                <KeyRound className="size-3.5" />
                                Revocar acceso
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap gap-2">
                                {commandTypes.map((type) => (
                                    <Button
                                        key={type.value}
                                        variant={type.requires_confirmation ? 'outline' : 'secondary'}
                                        size="sm"
                                        onClick={() => sendCommand(type)}
                                    >
                                        <Send className="size-3.5" />
                                        {type.label}
                                    </Button>
                                ))}
                            </div>

                            <DataTable
                                columns={commandColumns}
                                rows={commands}
                                keyExtractor={(row) => row.id}
                                empty={<EmptyState icon={Send} title="Sin comandos enviados" />}
                            />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="sync" className="mt-4">
                    <Card>
                        <CardContent className="pt-4">
                            <DataTable
                                columns={manifestColumns}
                                rows={manifests}
                                keyExtractor={(row) => row.id}
                                empty={<EmptyState icon={RefreshCw} title="Sin manifiestos generados" />}
                            />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="playback" className="mt-4">
                    <Card>
                        <CardContent className="pt-4">
                            <DataTable
                                columns={playbackColumns}
                                rows={playback}
                                keyExtractor={(row) => row.id}
                                empty={<EmptyState icon={PlayCircle} title="Sin reproducciones registradas" />}
                            />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="logs" className="mt-4">
                    <Card>
                        <CardContent className="pt-4">
                            <ul className="divide-y divide-line">
                                {heartbeats.length === 0 ? (
                                    <EmptyState icon={HardDrive} title="Sin latidos registrados" />
                                ) : (
                                    heartbeats.map((heartbeat, index) => (
                                        <li key={heartbeat.recorded_at ?? index} className="flex items-center justify-between py-2 text-xs">
                                            <span className="text-muted">{formatDateTime(heartbeat.recorded_at)}</span>
                                            <span className="text-faint">{heartbeat.player_status ?? '—'}</span>
                                            <span className="text-faint">{heartbeat.network_status ?? '—'}</span>
                                            <span className="metric text-muted">{formatBytes(heartbeat.available_storage)} libres</span>
                                        </li>
                                    ))
                                )}
                            </ul>
                        </CardContent>
                    </Card>
                </TabsContent>
                <TabsContent value="validation" className="mt-4 space-y-4">
                    <DeviceDiagnostics diagnostics={heartbeats[0]?.diagnostics} recordedAt={heartbeats[0]?.recorded_at} />
                    <Card><CardHeader><CardTitle>Errores de reproducción recientes</CardTitle></CardHeader><CardContent>
                        {failures.length === 0 ? <p className="text-sm text-muted">Sin errores de reproducción registrados.</p> :
                            <ul className="divide-y divide-line">{failures.map((event) => <li key={event.id} className="space-y-1 py-3 text-sm">
                                <p>{event.media ?? 'Archivo sin nombre'} · {formatDateTime(event.started_at)}</p>
                                <Badge tone="danger">{event.error_code}</Badge>
                                <p className="text-muted">{describeDeviceError(event.error_code ?? '')}</p>
                            </li>)}</ul>}
                    </CardContent></Card>
                    <Card><CardHeader><CardTitle>Errores de sincronización recientes</CardTitle></CardHeader><CardContent>
                        {heartbeats.some((h) => h.diagnostics?.sync_error) ? <ul className="divide-y divide-line">
                            {heartbeats.filter((h) => h.diagnostics?.sync_error).map((h, i) => <li key={h.recorded_at ?? i} className="space-y-1 py-3 text-sm">
                                <p>{formatDateTime(h.recorded_at)}</p><Badge tone="danger">{h.diagnostics?.sync_error}</Badge>
                                <p className="text-muted">{describeDeviceError(h.diagnostics!.sync_error!)}</p>
                            </li>)}
                        </ul> : <p className="text-sm text-muted">Sin errores en los reportes recientes.</p>}
                    </CardContent></Card>
                    <Card><CardHeader><CardTitle>Comandos fallidos recientes</CardTitle></CardHeader><CardContent>
                        {commands.some((c) => c.error) ? <ul className="divide-y divide-line">{commands.filter((c) => c.error).map((c) =>
                            <li key={c.id} className="py-3 text-sm"><p>{c.command.label} · {formatDateTime(c.created_at)}</p><p className="break-words text-danger">{c.error}</p></li>
                        )}</ul> : <p className="text-sm text-muted">Sin errores en los comandos recientes.</p>}
                    </CardContent></Card>
                </TabsContent>
            </Tabs>

            <ConfirmDialog
                open={!!pendingCommand}
                onOpenChange={(open) => (!open ? setPendingCommand(null) : null)}
                title={`Enviar ${pendingCommand?.label ?? ''}`}
                description="Esta acción puede interrumpir momentáneamente la reproducción en la pantalla."
                confirmLabel="Enviar comando"
                variant="primary"
                onConfirm={() => pendingCommand && executeCommand(pendingCommand)}
            />

            <ConfirmDialog
                open={confirmingRevoke}
                onOpenChange={setConfirmingRevoke}
                title="Revocar acceso"
                description="La pantalla perderá su token y deberá activarse de nuevo con un código."
                confirmLabel="Revocar acceso"
                onConfirm={() =>
                    router.post(`/admin/devices/${device.id}/revoke-token`, {}, {
                        preserveScroll: true,
                        onSuccess: () => toast.success('Acceso revocado.'),
                    })
                }
            />
            <ConfirmDialog
                open={confirmingDelete}
                onOpenChange={setConfirmingDelete}
                title="Eliminar pantalla"
                description={`¿Eliminar «${device.name}»? Se borrarán sus reportes, comandos y manifiestos. Perderá su acceso y necesitará una nueva activación.`}
                confirmLabel="Eliminar pantalla"
                onConfirm={() => router.delete(`/admin/devices/${device.id}`)}
            />
        </AdminLayout>
    );
}

function Row({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
    return (
        <div className="flex items-center justify-between gap-4 border-b border-line/60 pb-2 last:border-0">
            <span className="text-xs text-muted">{label}</span>
            <span className={`truncate text-right text-fg ${mono ? 'metric text-xs' : ''}`}>{value}</span>
        </div>
    );
}

const commandColumns: Array<Column<CommandEntity>> = [
    { key: 'command', header: 'Comando', cell: (row) => <span className="font-medium text-fg">{row.command.label}</span> },
    { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
    { key: 'created', header: 'Enviado', cell: (row) => <span className="text-muted">{formatRelative(row.created_at)}</span> },
    { key: 'executed', header: 'Ejecutado', cell: (row) => <span className="text-muted">{row.executed_at ? formatRelative(row.executed_at) : '—'}</span> },
    { key: 'by', header: 'Por', cell: (row) => <span className="text-muted">{row.created_by ?? 'Sistema'}</span> },
];

const manifestColumns: Array<Column<ManifestRow>> = [
    { key: 'version', header: 'Versión', cell: (row) => <span className="metric text-fg">{row.version}</span> },
    { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
    { key: 'checksum', header: 'Checksum', cell: (row) => <span className="metric truncate text-[11px] text-faint">{row.checksum?.slice(0, 16) ?? '—'}…</span> },
    { key: 'generated', header: 'Generado', cell: (row) => <span className="text-muted">{formatDateTime(row.generated_at)}</span> },
    { key: 'activated', header: 'Activado', cell: (row) => <span className="text-muted">{row.activated_at ? formatDateTime(row.activated_at) : '—'}</span> },
];

const playbackColumns: Array<Column<PlaybackRow>> = [
    { key: 'media', header: 'Creatividad', cell: (row) => <span className="truncate text-fg">{row.media ?? '—'}</span> },
    { key: 'campaign', header: 'Campaña', cell: (row) => <span className="text-muted">{row.campaign ?? 'Contenido propio'}</span> },
    { key: 'started', header: 'Inicio', cell: (row) => <span className="text-muted">{formatDateTime(row.started_at)}</span> },
    { key: 'duration', header: 'Duración', cell: (row) => <span className="metric text-muted">{row.duration_played}s</span> },
    {
        key: 'completed',
        header: 'Resultado',
        cell: (row) =>
            row.error_code ? (
                <Badge tone="danger">{row.error_code}</Badge>
            ) : row.completed ? (
                <Badge tone="positive">Completada</Badge>
            ) : (
                <Badge tone="warning">Parcial</Badge>
            ),
    },
];
