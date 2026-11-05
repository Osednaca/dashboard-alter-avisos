import type { ReactNode } from 'react';
import { cn } from '@/Utils/cn';

export function PageHeader({
    title,
    description,
    actions,
    eyebrow,
    className,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    eyebrow?: string;
    className?: string;
}) {
    return (
        <div className={cn('flex min-w-0 flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between', className)}>
            <div className="min-w-0">
                {eyebrow ? (
                    <p className="mb-1 text-xs font-semibold text-accent">{eyebrow}</p>
                ) : null}
                <h1 className="truncate text-xl font-semibold tracking-tight text-fg sm:text-2xl">
                    {title}
                </h1>
                {description ? (
                    <p className="mt-1 max-w-2xl text-sm text-muted">{description}</p>
                ) : null}
            </div>
            {actions ? <div className="flex min-w-0 max-w-full flex-wrap items-center gap-2">{actions}</div> : null}
        </div>
    );
}
