import { Image as ImageIcon, RefreshCw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/Components/ui/badge';
import { LivePreview } from '@/Components/app/LiveStreamDialog';
import type { DevicePreviewData, PlaybackZone, PlaybackZoneName, PreviewMediaData } from '@/Types/preview';
import { formatRelative } from '@/Utils/format';
import { cn } from '@/Utils/cn';
import { PLAYBACK_FRESH_MS, playbackIsFresh, playbackPositionMs, playbackSceneLabels } from '@/Utils/playback-preview';

export type ScreenPreviewData = DevicePreviewData;
const zoneLabels = { business: 'Negocio', advertising: 'Publicidad', fullscreen: 'Pantalla completa' };
const stateLabels = { playing: 'Reproduciendo', paused: 'Pausado', buffering: 'Cargando', error: 'Error en la TV', empty: 'Zona vacía' };
const sourceLabels = { manifest: 'Lista', quick_play: 'Reproducción inmediata', live: 'Directo', live_fallback: 'Reserva de directo', empty: 'Sin contenido' };

function PreviewMedia({ media, zone, ageMs = 0 }: { media: PreviewMediaData | null; zone?: PlaybackZone; ageMs?: number }) {
    const [failed, setFailed] = useState(false);
    const video = useRef<HTMLVideoElement>(null);
    useEffect(() => {
        const element = video.current;
        if (!zone || !element) return;
        const synchronize = () => {
            const target = playbackPositionMs(zone, ageMs);
            const duration = Math.min(zone.duration_ms ?? Infinity, Number.isFinite(element.duration) ? element.duration * 1000 : Infinity);
            if (target !== null && element.readyState >= 1) {
                const bounded = Math.min(target, duration);
                if (Math.abs(element.currentTime * 1000 - bounded) > 400) element.currentTime = bounded / 1000;
            }
            if (target === null || zone.state !== 'playing' || target >= duration || element.ended) element.pause();
            else void element.play().catch(() => {});
        };
        synchronize();
        element.addEventListener('loadedmetadata', synchronize);
        const stopAtBoundary = () => {
            if (zone.duration_ms != null && element.currentTime * 1000 >= zone.duration_ms) element.pause();
        };
        element.addEventListener('timeupdate', stopAtBoundary);
        return () => {
            element.removeEventListener('loadedmetadata', synchronize);
            element.removeEventListener('timeupdate', stopAtBoundary);
        };
    }, [zone, ageMs]);
    if (!media || failed || zone?.state === 'error' || zone?.state === 'empty') {
        return <div className="flex size-full flex-col items-center justify-center gap-2 p-3 text-center text-xs text-white/60">
            <ImageIcon className="size-4" />
            <span>{failed ? 'No se pudo cargar en este navegador' : zone ? stateLabels[zone.state] : 'Sin contenido confirmado en esta zona'}</span>
        </div>;
    }
    if (media.type === 'live_stream') {
        if (zone?.state === 'paused') return <div className="grid size-full place-items-center text-xs text-white/60">Directo pausado en la TV</div>;
        return media.live ? <LivePreview source={media.live} compact fit="cover" />
            : <span className="p-3 text-xs text-white/60">Directo sin fuente disponible</span>;
    }
    return media.type === 'video' ? (
        <video ref={video} src={media.url} className="size-full object-cover" muted autoPlay={!zone} loop={!zone} playsInline onError={() => setFailed(true)} />
    ) : (
        <img src={media.url} alt="Contenido de la pantalla" className="size-full object-cover" onError={() => setFailed(true)} />
    );
}

export function ScreenPreview({ preview, className }: { preview: DevicePreviewData | null; className?: string }) {
    const report = preview?.playback;
    const [elapsed, setElapsed] = useState(0);
    useEffect(() => {
        setElapsed(0);
        if (!report?.fresh) return;
        const start = performance.now();
        const tick = () => setElapsed(Math.max(0, performance.now() - start));
        const interval = window.setInterval(tick, 1000);
        const stale = window.setTimeout(tick, Math.max(0, PLAYBACK_FRESH_MS - report.age_ms + 1));
        return () => { window.clearInterval(interval); window.clearTimeout(stale); };
    }, [report, preview?.checked_at]);
    if (!preview) {
        return <div className="flex aspect-video items-center justify-center rounded-control border border-dashed border-line bg-inset text-xs text-muted">
            Sin pantallas para previsualizar.
        </div>;
    }
    const { device, layout } = preview;
    const fresh = report ? playbackIsFresh(report, elapsed) : false;
    const stale = !!report && !fresh;
    const sceneMasked = fresh && report?.scene !== 'playback';
    const portrait = layout ? layout.rotation % 180 !== 0 : false;
    const stacked = layout?.split === 'top_bottom';
    const fullscreen = fresh && !sceneMasked ? report?.zones.fullscreen : undefined;
    const ageMs = (report?.age_ms ?? 0) + elapsed;
    const zones: Array<{ key: PlaybackZoneName; percent: number; media: PreviewMediaData | null; state?: PlaybackZone }> = fullscreen
        ? [{ key: 'fullscreen', percent: 100, media: fullscreen.media, state: fullscreen }]
        : [
            { key: 'business', percent: layout?.business_percentage ?? 70, media: report ? report.zones.business?.media ?? null : preview.business_media, state: report?.zones.business },
            { key: 'advertising', percent: layout?.advertising_percentage ?? 30, media: report ? report.zones.advertising?.media ?? null : preview.advertising?.media ?? null, state: report?.zones.advertising },
        ];
    if (!fullscreen && layout && !layout.business_first) zones.reverse();
    const statusText = stale ? 'Reporte vencido o no verificable. No se puede confirmar qué muestra la TV ahora.'
        : sceneMasked && report ? playbackSceneLabels[report.scene]
        : fresh ? 'Contenido y estado reportados por la TV. Se consulta cada tres segundos; puede existir un pequeño desfase.'
        : preview.status === 'unconfirmed' ? 'La TV todavía no ha confirmado un manifiesto.'
        : 'Vista aproximada del contenido confirmado. Sin reporte de reproducción de la TV; no está sincronizada. Actualiza el APK para recibir el estado real.';
    const hasLive = fresh && zones.some(zone => zone.media?.type === 'live_stream');
    const aspect = layout?.width_px && layout.height_px ? `${layout.width_px} / ${layout.height_px}` : portrait ? '9 / 16' : '16 / 9';

    return <div className={cn('space-y-3', className)}>
        <div className="flex flex-wrap gap-2 text-xs text-fg">
            <Badge tone={fresh ? 'positive' : stale ? 'danger' : 'neutral'}>{fresh ? 'Estado reportado' : stale ? 'Reporte vencido' : 'Vista aproximada'}</Badge>
            <Badge>{layout ? `${portrait ? 'Vertical' : 'Horizontal'} · ${layout.rotation}°` : 'Orientación sin confirmar'}</Badge>
            {layout ? <Badge>{fullscreen ? 'Pantalla completa' : stacked ? 'Arriba / abajo' : 'Izquierda / derecha'} · {layout.ratio}</Badge> : null}
        </div>
        <div className={cn('mx-auto rounded-[20px] border border-line-strong bg-[#04080d] p-2.5 shadow-float', portrait ? 'w-full max-w-[280px]' : 'w-full')}>
            <div data-preview-orientation={portrait ? 'portrait' : 'landscape'} data-preview-split={layout?.split} data-preview-rotation={layout?.rotation}
                className={cn('relative flex overflow-hidden rounded-[12px] bg-black', stacked ? 'flex-col' : 'flex-row')}
                style={{ aspectRatio: aspect }}>
                {stale || sceneMasked ? <div className="flex size-full items-center justify-center p-4 text-center text-xs text-white/60">{statusText}</div>
                    : layout ? zones.map(zone => <div key={zone.key} data-preview-zone={!report || zone.state ? zone.key : undefined}
                        className="relative min-h-0 min-w-0 overflow-hidden border border-white/10 bg-black"
                        style={{ flex: `${zone.percent} 1 0%` }}>
                        {!report || zone.state ? <>
                            <PreviewMedia key={zone.media ? `${zone.state?.source}:${zone.state?.item_id}:${zone.media.id}:${zone.media.url}` : 'empty'} media={zone.media} zone={zone.state} ageMs={ageMs} />
                            <span className="pointer-events-none absolute bottom-1 left-1 rounded bg-black/80 px-1.5 py-0.5 text-[10px] text-white">
                                {zoneLabels[zone.key]} · {zone.percent}%{zone.state ? ` · ${sourceLabels[zone.state.source]} · ${stateLabels[zone.state.state]}` : ''}
                            </span>
                        </> : null}
                    </div>) : <div className="flex size-full items-center justify-center p-4 text-center text-xs text-white/60">Diseño y contenido aún sin confirmar</div>}
            </div>
        </div>
        <p role="status" className="text-xs text-muted">{statusText}{preview.pending_manifest_version ? ' Hay cambios pendientes de confirmar.' : ''}</p>
        {hasLive ? <p className="text-xs text-muted">El directo del navegador usa su propio búfer y puede mostrar un fotograma distinto al de la TV.</p> : null}
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
                <p className="truncate text-sm font-medium text-fg">{device.name}</p>
                <p className="truncate text-xs text-muted">{device.business?.name ?? '—'}{device.location ? ` · ${device.location.name}, ${device.location.city}` : ''}</p>
            </div>
            <Badge tone={device.is_online ? 'positive' : 'danger'} dot>{device.is_online ? 'En línea' : 'Desconectada'}</Badge>
        </div>
        <dl className="grid grid-cols-2 gap-3 border-t border-line pt-3 text-xs">
            <div><dt className="text-faint">{report ? 'Diseño reportado' : 'Diseño confirmado'}</dt><dd className="text-fg">{layout?.name ?? '—'}</dd></div>
            <div><dt className="text-faint">Lista del negocio</dt><dd className="text-fg">{preview.playlist?.name ?? '—'}</dd></div>
            <div><dt className="text-faint">{report ? 'Publicidad reportada' : 'Publicidad de muestra'}</dt><dd className="text-fg">{preview.advertising?.campaign_name ?? '—'}</dd></div>
            <div><dt className="text-faint">{report ? 'Manifiesto del diseño' : 'Manifiesto confirmado'}</dt><dd className="metric text-fg">{preview.manifest_version ?? '—'}</dd></div>
            <div><dt className="text-faint">Última sincronización</dt><dd className="text-fg">{formatRelative(preview.last_sync_at)}</dd></div>
            <div><dt className="text-faint">{report ? 'Reporte recibido' : 'Consulta del dashboard'}</dt><dd className="flex items-center gap-1 text-fg"><RefreshCw className="size-3" />{formatRelative(report?.received_at ?? preview.checked_at)}</dd></div>
        </dl>
    </div>;
}
