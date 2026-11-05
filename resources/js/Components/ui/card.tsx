import * as React from 'react';
import { cn } from '@/Utils/cn';

export function Card({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn(
                'min-w-0 rounded-card border border-line bg-card',
                className,
            )}
            {...props}
        />
    );
}

export function CardHeader({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('flex min-w-0 flex-wrap items-start justify-between gap-3 px-5 pt-4 pb-3', className)}
            {...props}
        />
    );
}

export function CardTitle({ className, ...props }: React.HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h3 className={cn('text-sm font-semibold tracking-tight text-fg', className)} {...props} />
    );
}

export function CardDescription({ className, ...props }: React.HTMLAttributes<HTMLParagraphElement>) {
    return <p className={cn('text-xs text-muted', className)} {...props} />;
}

export function CardContent({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('px-5 pb-5', className)} {...props} />;
}

export function CardFooter({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('flex items-center gap-3 border-t border-line px-5 py-3', className)}
            {...props}
        />
    );
}
