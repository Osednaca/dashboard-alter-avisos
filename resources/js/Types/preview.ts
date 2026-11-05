import type { DeviceEntity } from '@/Types';
import type { LiveSource } from '@/Components/app/LiveStreamDialog';

export interface PreviewMediaData {
    id: number;
    type: 'image' | 'video' | 'live_stream';
    url: string;
    mime_type: string;
    live?: LiveSource | null;
}

export interface DevicePreviewData {
    device: DeviceEntity;
    layout: {
        id: number | null;
        name: string;
        orientation: string | null;
        rotation: number;
        split: 'top_bottom' | 'side_by_side';
        business_first: boolean;
        business_percentage: number;
        advertising_percentage: number;
        ratio: string;
        width_px?: number;
        height_px?: number;
    } | null;
    business_media: PreviewMediaData | null;
    advertising: { campaign_name: string; media: PreviewMediaData } | null;
    playlist: { id: number; name: string } | null;
    last_sync_at: string | null;
    manifest_version: string | null;
    pending_manifest_version: string | null;
    status: 'approximate' | 'unconfirmed' | 'reported' | 'stale';
    playback_reported: boolean;
    checked_at: string;
    playback?: PlaybackReport | null;
}

export type PlaybackZoneName = 'business' | 'advertising' | 'fullscreen';
export interface PlaybackZone {
    source: 'manifest' | 'quick_play' | 'live' | 'live_fallback' | 'empty';
    state: 'playing' | 'paused' | 'buffering' | 'error' | 'empty';
    manifest_version?: string | null;
    item_id?: string | null;
    media_asset_id?: number | null;
    quick_play_device_id?: number | null;
    command_id?: number | null;
    position_ms?: number | null;
    duration_ms?: number | null;
    live_state?: string | null;
    live_creative_id?: number | null;
    media: PreviewMediaData | null;
    campaign_name?: string | null;
}
export interface PlaybackReport {
    fresh: boolean;
    received_at: string;
    age_ms: number;
    session_id: string;
    sequence: number;
    scene: 'playback' | 'settings' | 'pin' | 'background' | 'activation';
    layout: {
        manifest_version?: string | null;
        rotation: number;
        split: 'top_bottom' | 'side_by_side';
        business_percentage: number;
        business_first: boolean;
        width_px: number;
        height_px: number;
    } | null;
    zones: Partial<Record<PlaybackZoneName, PlaybackZone>>;
}
