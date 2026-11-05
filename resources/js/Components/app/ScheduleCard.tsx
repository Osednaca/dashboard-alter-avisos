import { Clock, MapPin, Pencil, Trash2 } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import type { ContentScheduleEntity } from '@/Types';
import { formatTime } from '@/Utils/format';

export function ScheduleCard({
    schedule,
    canManage,
    onEdit,
    onDelete,
}: {
    schedule: ContentScheduleEntity;
    canManage: boolean;
    onEdit: (schedule: ContentScheduleEntity) => void;
    onDelete: (schedule: ContentScheduleEntity) => void;
}) {
    return (
        <div className="flex min-w-0 flex-wrap items-start gap-3 rounded-card border border-line bg-card p-4">
            <span
                className={`flex size-10 shrink-0 items-center justify-center rounded-control border ${
                    schedule.is_active_now ? 'border-accent/40 bg-accent/10 text-accent' : 'border-line bg-surface text-faint'
                }`}
            >
                <Clock className="size-4" />
            </span>

            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="truncate text-sm font-medium text-fg">{schedule.name}</p>
                    {schedule.is_active_now ? <Badge tone="positive" dot>En curso</Badge> : null}
                    {schedule.status !== 'active' ? <Badge tone="neutral">Inactiva</Badge> : null}
                </div>
                <p className="mt-0.5 text-xs text-muted">
                    {!schedule.daily_start_time && !schedule.daily_end_time ? 'Todo el día' : `${formatTime(schedule.daily_start_time)} – ${formatTime(schedule.daily_end_time)}`}
                    {schedule.items ? ` · ${schedule.items.length} elementos` : ''}
                </p>
                <p className="mt-0.5 flex items-center gap-1 text-xs text-muted">
                    <MapPin className="size-3 shrink-0" />
                    {schedule.location?.name ?? 'Todas las ubicaciones'}
                </p>
            </div>

            {canManage ? (
                <div className="flex items-center gap-1">
                    <Button variant="ghost" size="icon-sm" onClick={() => onEdit(schedule)} aria-label="Editar">
                        <Pencil className="size-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon-sm" onClick={() => onDelete(schedule)} aria-label="Eliminar">
                        <Trash2 className="size-3.5 text-danger" />
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
