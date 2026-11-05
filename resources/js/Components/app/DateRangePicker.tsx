import { router } from '@inertiajs/react';
import { Calendar, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { cn } from '@/Utils/cn';

const presets = [
    { label: '7 días', days: 7 },
    { label: '30 días', days: 30 },
    { label: '90 días', days: 90 },
];

function toInput(date: Date): string {
    return date.toISOString().slice(0, 10);
}

export function DateRangePicker({
    from,
    to,
    routeName,
    extra = {},
    className,
}: {
    from: string;
    to: string;
    routeName: string;
    extra?: Record<string, string | number | null | undefined>;
    className?: string;
}) {
    const [open, setOpen] = useState(false);
    const [draftFrom, setDraftFrom] = useState(from);
    const [draftTo, setDraftTo] = useState(to);

    const apply = (nextFrom: string, nextTo: string) => {
        setOpen(false);
        router.get(routeName, { ...extra, from: nextFrom, to: nextTo }, { preserveState: true, preserveScroll: true });
    };

    const label = `${new Date(from).toLocaleDateString('es-CO', { day: '2-digit', month: 'short' })} – ${new Date(to).toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' })}`;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" size="sm" className={cn('justify-between gap-2 font-normal', className)}>
                    <Calendar className="size-3.5 text-faint" />
                    <span className="metric text-xs">{label}</span>
                    <ChevronDown className="size-3.5 text-faint" />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end">
                <div className="space-y-3">
                    <div className="flex flex-wrap gap-1.5">
                        {presets.map((preset) => {
                            const nextTo = toInput(new Date());
                            const nextFrom = toInput(new Date(Date.now() - (preset.days - 1) * 86400000));
                            return (
                                <Button
                                    key={preset.days}
                                    size="sm"
                                    variant="secondary"
                                    onClick={() => {
                                        setDraftFrom(nextFrom);
                                        setDraftTo(nextTo);
                                        apply(nextFrom, nextTo);
                                    }}
                                >
                                    {preset.label}
                                </Button>
                            );
                        })}
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <label className="space-y-1">
                            <span className="text-[11px] text-faint">Desde</span>
                            <input
                                type="date"
                                value={draftFrom}
                                onChange={(event) => setDraftFrom(event.target.value)}
                                className="h-9 w-full rounded-control border border-line bg-inset px-2 text-xs text-fg focus:outline-none"
                            />
                        </label>
                        <label className="space-y-1">
                            <span className="text-[11px] text-faint">Hasta</span>
                            <input
                                type="date"
                                value={draftTo}
                                onChange={(event) => setDraftTo(event.target.value)}
                                className="h-9 w-full rounded-control border border-line bg-inset px-2 text-xs text-fg focus:outline-none"
                            />
                        </label>
                    </div>
                    <Button size="sm" className="w-full" onClick={() => apply(draftFrom, draftTo)}>
                        Aplicar rango
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
