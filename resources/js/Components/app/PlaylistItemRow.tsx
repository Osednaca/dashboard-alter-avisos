import { ArrowDown, ArrowUp, GripVertical, Trash2 } from 'lucide-react';
import { useState, type DragEvent } from 'react';
import { MediaThumbnail } from '@/Components/app/MediaThumbnail';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import type { Option, PlaylistItemEntity } from '@/Types';
import { cn } from '@/Utils/cn';

export function PlaylistItemRow({
    item,
    index,
    total,
    transitions,
    canManage,
    onUpdate,
    onRemove,
    onMove,
    dragging = false,
    onDragStart,
    onDragOver,
    onDrop,
}: {
    item: PlaylistItemEntity;
    index: number;
    total: number;
    transitions: Option[];
    canManage: boolean;
    onUpdate: (id: number, data: { duration: number; transition: string }) => void;
    onRemove: (id: number) => void;
    onMove: (id: number, direction: -1 | 1) => void;
    dragging?: boolean;
    onDragStart?: (id: number) => void;
    onDragOver?: (event: DragEvent<HTMLDivElement>) => void;
    onDrop?: (id: number) => void;
}) {
    const [duration, setDuration] = useState(item.duration);

    const commitDuration = () => {
        const value = Math.min(600, Math.max(3, Number(duration) || item.duration));
        setDuration(value);
        if (value !== item.duration) {
            onUpdate(item.id, { duration: value, transition: item.transition });
        }
    };

    return (
        <div
            onDragOver={onDragOver}
            onDrop={() => onDrop?.(item.id)}
            className={cn(
                'flex min-w-0 flex-col gap-3 rounded-control border bg-surface p-3 transition-colors sm:flex-row sm:items-center',
                dragging ? 'border-accent/60 opacity-60' : 'border-line',
            )}
        >
            <div className="flex min-w-0 flex-1 items-center gap-3">
                {canManage ? (
                    <span
                        draggable
                        onDragStart={() => onDragStart?.(item.id)}
                        className="cursor-grab text-faint active:cursor-grabbing"
                        aria-label="Reordenar"
                    >
                        <GripVertical className="size-4" />
                    </span>
                ) : null}

                <span className="metric w-5 shrink-0 text-center text-xs text-faint">{index + 1}</span>
                <MediaThumbnail media={item.media} className="w-16 shrink-0 sm:w-20" />

                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm text-fg">{item.media?.filename ?? 'Contenido eliminado'}</p>
                    <p className="text-[11px] text-faint">
                        {item.media?.type?.label ?? '—'}
                        {item.media?.resolution ? ` · ${item.media.resolution}` : ''}
                    </p>
                </div>
            </div>

            <div className="flex min-w-0 flex-wrap items-center justify-between gap-2 sm:ml-auto sm:justify-end">
                {canManage ? (
                    <>
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="flex items-center gap-1">
                                <Input
                                    type="number"
                                    min={3}
                                    max={600}
                                    value={duration}
                                    onChange={(event) => setDuration(Number(event.target.value))}
                                    onBlur={commitDuration}
                                    className="h-9 w-16 px-2 text-xs sm:h-8"
                                    aria-label="Duración en segundos"
                                />
                                <span className="text-[11px] text-faint">seg</span>
                            </div>
                            <Select
                                value={item.transition}
                                onValueChange={(value) => onUpdate(item.id, { duration, transition: value })}
                            >
                                <SelectTrigger className="h-9 w-32 text-xs sm:h-8">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {transitions.map((transition) => (
                                        <SelectItem key={transition.value} value={transition.value}>
                                            {transition.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex items-center gap-0.5">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                disabled={index === 0}
                                onClick={() => onMove(item.id, -1)}
                                aria-label="Subir"
                            >
                                <ArrowUp className="size-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                disabled={index === total - 1}
                                onClick={() => onMove(item.id, 1)}
                                aria-label="Bajar"
                            >
                                <ArrowDown className="size-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={() => onRemove(item.id)}
                                aria-label="Quitar"
                            >
                                <Trash2 className="size-3.5 text-danger" />
                            </Button>
                        </div>
                    </>
                ) : (
                    <span className="metric text-xs text-muted">{item.duration} seg</span>
                )}
            </div>
        </div>
    );
}
