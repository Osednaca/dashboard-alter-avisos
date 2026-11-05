import { ArrowDownRight, ArrowUpRight, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card } from '@/Components/ui/card';
import { formatNumber } from '@/Utils/format';
import { cn } from '@/Utils/cn';

interface StatCardProps {
    label: string;
    value: number | string;
    total?: number;
    trend?: number | null;
    hint?: string;
    icon: LucideIcon;
    meter?: number;
    delay?: number;
}

export function StatCard({
    label,
    value,
    total,
    trend,
    hint,
    icon: Icon,
    meter,
    delay = 0,
}: StatCardProps): ReactNode {
    const positive = (trend ?? 0) >= 0;

    return (
        <Card className="relative overflow-hidden">
            <div className="flex items-start gap-3 p-4 sm:gap-4">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-control border border-line bg-surface text-accent sm:size-11">
                    <Icon className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <p className="text-xs font-medium text-muted">{label}</p>
                        {trend !== null && trend !== undefined ? (
                            <span
                                className={cn(
                                    'inline-flex items-center gap-0.5 text-xs font-semibold',
                                    positive ? 'text-positive' : 'text-danger',
                                )}
                            >
                                {positive ? (
                                    <ArrowUpRight className="size-3.5" />
                                ) : (
                                    <ArrowDownRight className="size-3.5" />
                                )}
                                {positive ? '+' : ''}
                                {trend}%
                            </span>
                        ) : null}
                    </div>
                    <p className="metric mt-1 text-2xl font-semibold leading-none text-fg">
                        {typeof value === 'number' ? formatNumber(value) : value}
                    </p>
                    <p className="mt-1.5 text-xs text-faint">
                        {total !== undefined
                            ? `de ${formatNumber(total)} ${hint ?? ''}`.trim()
                            : hint}
                    </p>
                </div>
            </div>
            <div className="px-4 pb-3">
                <div className="h-1 w-full overflow-hidden rounded-full bg-elevated">
                    <div
                        className="meter h-full rounded-full transition-[width] duration-700 ease-out"
                        style={{
                            width: `${Math.min(100, Math.max(4, meter ?? (total ? ((typeof value === 'number' ? value : 0) / total) * 100 : 30)))}%`,
                            transitionDelay: `${delay}ms`,
                        }}
                    />
                </div>
            </div>
        </Card>
    );
}
