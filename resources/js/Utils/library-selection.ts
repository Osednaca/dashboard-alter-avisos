import type { MediaEntity } from '@/Types';

export function canSelectLibraryMedia(media: MediaEntity): boolean {
    return media.processing_status.value === 'ready' && ['image', 'video'].includes(media.type.value);
}

export function toggleLibrarySelection(ids: number[], id: number, limit: number): number[] {
    if (ids.includes(id)) return ids.filter((value) => value !== id);
    return ids.length >= limit ? ids : [...ids, id];
}
