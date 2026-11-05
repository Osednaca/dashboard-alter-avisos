import { Image as ImageIcon, Play } from 'lucide-react';
import { useState } from 'react';
import type { MediaEntity } from '@/Types';
import { cn } from '@/Utils/cn';

export function QuickPlayPreview({
    media,
    displayMode,
    className,
}: {
    media: Pick<MediaEntity, 'url' | 'type' | 'filename' | 'formatted_duration'> | null;
    displayMode: string;
    className?: string;
}) {
    const isVideo = media?.type?.value === 'video';
    const mode = displayMode;
    const [failedUrl, setFailedUrl] = useState<string | null>(null);

    const mediaLayer = media && failedUrl === media.url ? (
        <span role="status" className="p-3 text-center text-xs text-white/70">Vista previa no disponible</span>
    ) : media ? (
        isVideo ? (
            <video
                key={media.url}
                src={media.url}
                className="size-full object-cover"
                muted
                autoPlay
                loop
                playsInline
                onError={() => setFailedUrl(media.url)}
            />
        ) : (
            <img
                src={media.url}
                alt={media.filename}
                className="size-full object-cover"
                onError={() => setFailedUrl(media.url)}
            />
        )
    ) : (
        <span className="flex size-full items-center justify-center text-[10px] text-white/40">
            Selecciona un contenido
        </span>
    );

    return (
        <div className={cn('rounded-[20px] border border-line-strong bg-[#04080d] p-2.5 shadow-float', className)}>
            <div className="relative flex aspect-video overflow-hidden rounded-[12px] bg-black">
                {mode === 'fullscreen' ? (
                    <div className="relative flex size-full items-center justify-center">
                        {mediaLayer}
                        <span className="absolute left-2 top-2 rounded bg-accent px-1.5 py-0.5 text-[10px] font-semibold text-[#20170a]">
                            Pantalla completa
                        </span>
                    </div>
                ) : mode === 'advertising' ? (
                    <>
                        <div className="relative flex w-[70%] items-center justify-center bg-[#0a0f16]">
                            <span className="text-[10px] text-white/30">Contenido del negocio</span>
                        </div>
                        <div className="relative flex w-[30%] items-center justify-center border-l border-white/10 bg-black">
                            {mediaLayer}
                            <span className="absolute bottom-1.5 right-1.5 rounded bg-black/70 px-1.5 py-0.5 text-[9px] text-accent">
                                Publicidad
                            </span>
                        </div>
                    </>
                ) : (
                    <>
                        <div className="relative flex w-[70%] items-center justify-center bg-black">
                            {mediaLayer}
                            <span className="absolute bottom-1.5 left-1.5 rounded bg-black/70 px-1.5 py-0.5 text-[9px] text-white/80">
                                Contenido propio
                            </span>
                        </div>
                        <div className="relative flex w-[30%] items-center justify-center border-l border-white/10 bg-[#0a0f16]">
                            <span className="text-[10px] text-white/30">Publicidad</span>
                        </div>
                    </>
                )}
            </div>
            <div className="mx-auto mt-2 h-1 w-16 rounded-full bg-line-strong" />
            <div className="mt-2 flex items-center justify-between px-1 text-[10px] text-faint">
                <span className="flex items-center gap-1.5 truncate">
                    {isVideo ? <Play className="size-3" /> : <ImageIcon className="size-3" />}
                    <span className="truncate">{media?.filename ?? 'Sin contenido'}</span>
                </span>
                {media?.formatted_duration ? <span className="metric">{media.formatted_duration}</span> : null}
            </div>
        </div>
    );
}
