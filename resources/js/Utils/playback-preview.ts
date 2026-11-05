import type { PlaybackReport, PlaybackZone } from '@/Types/preview';

export const PLAYBACK_FRESH_MS = 15000;

/** Add only elapsed browser time; a skewed local wall clock cannot renew a report. */
export function playbackIsFresh(report: PlaybackReport, elapsedMs = 0): boolean {
    return report.fresh && report.age_ms + Math.max(0, elapsedMs) <= PLAYBACK_FRESH_MS;
}

/** Stop at this item's boundary. Never loop or predict a next playlist item. */
export function playbackPositionMs(zone: PlaybackZone, reportAgeMs: number): number | null {
    if (zone.position_ms == null || !Number.isFinite(zone.position_ms)) return null;
    const elapsed = zone.state === 'playing' ? Math.max(0, Math.min(PLAYBACK_FRESH_MS, reportAgeMs)) : 0;
    const duration = zone.duration_ms ?? 86400000;
    return Math.min(duration, Math.max(0, zone.position_ms + elapsed));
}

export const playbackSceneLabels: Record<PlaybackReport['scene'], string> = {
    playback: 'Reproducción', settings: 'Configuración abierta en la TV',
    pin: 'Acceso administrativo en la TV', background: 'Aplicación en segundo plano',
    activation: 'TV en activación',
};
