import { Image as ImageIcon, Play } from 'lucide-react';
import { useState } from 'react';
import type { MediaEntity } from '@/Types';
import { cn } from '@/Utils/cn';

export function MediaThumbnail({
    media,
    className,
}: {
    media: Pick<MediaEntity, 'url' | 'thumbnail_url' | 'type' | 'filename'> | null | undefined;
    className?: string;
}) {
    const isVideo = media?.type?.value === 'video';
    const [failedUrl, setFailedUrl] = useState<string | null>(null);
    const [failedVideoUrl, setFailedVideoUrl] = useState<string | null>(null);
    const videoUrl = media?.url;
    const posterUrl = !isVideo || media?.thumbnail_url !== videoUrl ? media?.thumbnail_url : null;

    return (
        <div
            className={cn(
                'relative flex aspect-video items-center justify-center overflow-hidden rounded-control border border-line bg-inset',
                className,
            )}
        >
            {media?.type.value === 'live_stream' ? <span className="text-xs font-medium text-accent">EN VIVO</span> : posterUrl && failedUrl !== posterUrl ? (
                <img
                    src={posterUrl}
                    alt={media?.filename ?? ''}
                    loading="lazy"
                    onError={() => setFailedUrl(posterUrl)}
                    className="size-full object-cover"
                />
            ) : isVideo && videoUrl && failedVideoUrl !== videoUrl ? (
                <video
                    key={videoUrl}
                    src={`${videoUrl.split('#')[0]}#t=0.1`}
                    aria-label={media?.filename}
                    preload="metadata"
                    muted
                    playsInline
                    onError={() => setFailedVideoUrl(videoUrl)}
                    className="pointer-events-none size-full object-cover"
                />
            ) : (
                <span className="text-faint">
                    {isVideo ? <Play className="size-5" /> : <ImageIcon className="size-5" />}
                </span>
            )}
            {isVideo ? (
                <span className="absolute bottom-1.5 right-1.5 flex size-6 items-center justify-center rounded-full bg-black/70 text-accent">
                    <Play className="size-3" />
                </span>
            ) : null}
        </div>
    );
}
