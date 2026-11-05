import * as React from 'react';
import { cn } from '@/Utils/cn';

const tones = {
    positive: 'bg-positive/12 text-positive border-positive/25',
    warning: 'bg-warning/12 text-warning border-warning/25',
    danger: 'bg-danger/12 text-danger border-danger/25',
    info: 'bg-info/12 text-info border-info/25',
    neutral: 'bg-elevated text-muted border-line',
    accent: 'bg-accent/12 text-accent border-accent/25',
} as const;

export type BadgeTone = keyof typeof tones;

export function Badge({
    tone = 'neutral',
    className,
    dot = false,
    children,
    ...props
}: React.HTMLAttributes<HTMLSpanElement> & { tone?: BadgeTone; dot?: boolean }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium',
                tones[tone],
                className,
            )}
            {...props}
        >
            {dot ? <span className="size-1.5 rounded-full bg-current" /> : null}
            {children}
        </span>
    );
}
