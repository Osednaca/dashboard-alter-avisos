import type { ReactNode } from 'react';
import { cn } from '@/Utils/cn';

export function FormField({
    label,
    htmlFor,
    error,
    hint,
    children,
    className,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <label htmlFor={htmlFor} className="block text-xs font-medium text-muted">
                {label}
            </label>
            {children}
            {hint && !error ? <p className="text-[11px] text-faint">{hint}</p> : null}
            {error ? <p className="text-[11px] text-danger">{error}</p> : null}
        </div>
    );
}

export function FormSection({
    title,
    description,
    children,
    columns = 2,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    columns?: 1 | 2 | 3;
}) {
    return (
        <section className="space-y-4">
            <div>
                <h3 className="text-sm font-semibold text-fg">{title}</h3>
                {description ? <p className="text-xs text-muted">{description}</p> : null}
            </div>
            <div
                className={cn(
                    'grid gap-4',
                    columns === 1 && 'grid-cols-1',
                    columns === 2 && 'grid-cols-1 sm:grid-cols-2',
                    columns === 3 && 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
                )}
            >
                {children}
            </div>
        </section>
    );
}
