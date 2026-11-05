import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
}: {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 rounded-card border border-dashed border-line bg-surface/40 px-6 py-14 text-center">
            {Icon ? (
                <span className="flex size-12 items-center justify-center rounded-full border border-line bg-card text-faint">
                    <Icon className="size-5" />
                </span>
            ) : null}
            <div>
                <p className="text-sm font-medium text-fg">{title}</p>
                {description ? <p className="mt-1 text-xs text-muted">{description}</p> : null}
            </div>
            {action}
        </div>
    );
}
