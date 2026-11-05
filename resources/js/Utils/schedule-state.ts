import type { ContentScheduleEntity, MediaEntity, PlaylistItemEntity } from '@/Types';

export type ScheduleDraftItem = { key: number; media_asset_id: number; duration_seconds: number; transition: string };
export type LegacyScheduleContent = { id: number; name: string; items: PlaylistItemEntity[] };
export type ScheduleAssets = Record<number, MediaEntity>;

export function hydrateSchedule(schedule: ContentScheduleEntity | null, imported?: LegacyScheduleContent, selectedMedia: MediaEntity[] = []) {
    return {
        name: schedule?.name ?? imported?.name ?? '',
        location_id: schedule?.location ? String(schedule.location.id) : '',
        daily_start_time: schedule?.daily_start_time?.slice(0, 5) ?? '',
        daily_end_time: schedule?.daily_end_time?.slice(0, 5) ?? '',
        days_of_week: [...(schedule?.days_of_week ?? [])],
        status: schedule?.status ?? 'active',
        priority: schedule?.priority ?? 0,
        items: schedule || imported ? (schedule?.items ?? imported?.items ?? []).map((item, index): ScheduleDraftItem => ({
            key: index, media_asset_id: item.media?.id ?? 0,
            duration_seconds: item.duration, transition: item.transition,
        })) : selectedMedia.map((media, key): ScheduleDraftItem => ({
            key, media_asset_id: media.id, duration_seconds: Math.min(600, Math.max(3, Math.round(media.duration ?? 10))), transition: 'none',
        })),
    };
}

export type ScheduleDraft = ReturnType<typeof hydrateSchedule>;

export function appendScheduleItems(draft: ScheduleDraft, items: ScheduleDraftItem[]): ScheduleDraft {
    return { ...draft, items: [...draft.items, ...items] };
}

export function moveScheduleItem(draft: ScheduleDraft, index: number, direction: -1 | 1): ScheduleDraft {
    const destination = index + direction;
    if (index < 0 || index >= draft.items.length || destination < 0 || destination >= draft.items.length) return draft;
    const items = [...draft.items];
    [items[index], items[destination]] = [items[destination], items[index]];
    return { ...draft, items };
}

export function removeScheduleItem(draft: ScheduleDraft, key: number): ScheduleDraft {
    return { ...draft, items: draft.items.filter((item) => item.key !== key) };
}

export function schedulePayload(draft: ScheduleDraft) {
    return {
        ...draft,
        items: draft.items.map(({ media_asset_id, duration_seconds, transition }) => ({ media_asset_id, duration_seconds, transition })),
    };
}

export function hasUnreadyScheduleItems(items: ScheduleDraftItem[], assets: ScheduleAssets): boolean {
    return items.some((item) => !assets[item.media_asset_id]
        || assets[item.media_asset_id].processing_status.value !== 'ready'
        || !['image', 'video'].includes(assets[item.media_asset_id].type.value));
}

/** A successful scoped response omitting an ID means it is no longer available. */
export function applyScheduleMediaStatus(assets: ScheduleAssets, requestedIds: number[], media: MediaEntity[]): ScheduleAssets {
    const next = { ...assets };
    requestedIds.forEach((id) => { delete next[id]; });
    const allowed = new Set(requestedIds);
    media.forEach((asset) => { if (allowed.has(asset.id)) next[asset.id] = asset; });
    return next;
}
