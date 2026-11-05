import { cn } from '@/Utils/cn';

export function Skeleton({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('animate-pulse rounded-control bg-elevated/70', className)}
            {...props}
        />
    );
}
