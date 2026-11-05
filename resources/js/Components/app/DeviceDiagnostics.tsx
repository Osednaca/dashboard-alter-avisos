import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatBytes, formatDateTime } from '@/Utils/format';

export interface DeviceDiagnosticsData {
    current_content_type?: string;
    live_provider?: string;
    stream_state?: string;
    sync_error?: string | null;
    last_sync_at?: number | null;
    business_images_expected?: number | null;
    business_images_downloaded?: number | null;
    business_images_bytes?: number | null;
    media_directory?: string | null;
}

export function describeDeviceError(code: string): string {
    const reason = code.replace(/^MEDIA_\d+_/, '');
    const messages: Record<string, string> = {
        LOW_STORAGE: 'El TV no tiene espacio suficiente para descargar contenido.',
        CHECKSUM_MISMATCH: 'El archivo descargado no coincide con el original. Se reintentará.',
        MIME_MISMATCH: 'El servidor entregó un tipo de archivo distinto al esperado.',
        NETWORK_OR_STORAGE_ERROR: 'No se pudo descargar o guardar el contenido. Revisa la conexión y el almacenamiento del TV.',
        LOCAL_ASSET_CORRUPT_OR_MISSING: 'El archivo local falta o está dañado. Sincroniza el TV.',
        VIDEO_PLAYBACK_ERROR: 'No se pudo reproducir el video.',
        VIDEO_STALLED: 'El video dejó de avanzar.',
        LIVE_STREAM_FAILED: 'El directo no está disponible. El TV usa respaldo y reintenta automáticamente.',
        IMAGE_DECODE_ERROR: 'El TV no pudo abrir la imagen.',
        MEDIA_LOAD_TIMEOUT: 'El contenido tardó demasiado en cargar.',
        PROCESS_INTERRUPTED: 'La app se cerró durante la reproducción.',
        PLAYER_STOPPED: 'La reproducción se interrumpió al salir del reproductor.',
        STALE_MANIFEST: 'El servidor entregó una versión anterior del contenido.',
        MISSING_MANIFEST: 'El servidor no entregó un manifiesto de contenido.',
        SYNC_FAILED: 'No se pudo completar la sincronización.',
    };
    const prefix = code.match(/^MEDIA_(\d+)_/)?.[1];
    const message = messages[reason] ?? (reason.includes('HTTP_') ? 'El servidor rechazó la solicitud de contenido.' : 'El TV reportó un error. Revisa el código y vuelve a sincronizar.');
    return `${prefix ? `Archivo ${prefix}: ` : ''}${message}`;
}

export function DeviceDiagnostics({ diagnostics, recordedAt }: { diagnostics?: DeviceDiagnosticsData | null; recordedAt?: string | null }) {
    return (
        <Card>
            <CardHeader><CardTitle>Estado reportado por el TV</CardTitle></CardHeader>
            <CardContent className="space-y-3 text-sm">
                {!diagnostics ? <p className="text-muted">Sin diagnóstico local recibido. Instala la app 0.1.9 o posterior y espera su próxima conexión.</p> : <>
                    <p className="text-muted">Último reporte: {formatDateTime(recordedAt ?? null)}. Sin Internet, el reporte se actualiza cuando el TV vuelve a conectarse.</p>
                    {diagnostics.current_content_type === 'live_stream' ? <p>Directo · {diagnostics.live_provider?.toUpperCase()} · {({live:'En vivo', connecting:'Conectando', buffering:'Cargando', offline:'Sin conexión', failed:'Usando respaldo', ended:'Finalizado', unverified:'Reproductor sin confirmación', scheduled:'Programado'} as Record<string,string>)[diagnostics.stream_state ?? ''] ?? diagnostics.stream_state}</p> : null}
                    {diagnostics.sync_error ? <div role="alert" className="space-y-2 rounded-control border border-danger p-3">
                        <Badge tone="danger">{diagnostics.sync_error}</Badge>
                        <p>{describeDeviceError(diagnostics.sync_error)}</p>
                    </div> : <p>Sin error de sincronización en este reporte.</p>}
                    <p>Imágenes del negocio disponibles sin Internet: <strong>{diagnostics.business_images_downloaded ?? '—'} de {diagnostics.business_images_expected ?? '—'}</strong></p>
                    <p>Espacio ocupado: {formatBytes(diagnostics.business_images_bytes ?? null)}</p>
                    {diagnostics.media_directory && <p className="break-all text-muted">Carpeta privada del TV: {diagnostics.media_directory}</p>}
                    <p className="text-muted">Los originales se conservan en el panel. La reproducción usa las copias descargadas y verificadas en el TV.</p>
                </>}
            </CardContent>
        </Card>
    );
}
