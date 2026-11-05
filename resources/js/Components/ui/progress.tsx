import * as ProgressPrimitive from '@radix-ui/react-progress';
import * as React from 'react';
import { cn } from '@/Utils/cn';

export const Progress = React.forwardRef<
    React.ElementRef<typeof ProgressPrimitive.Root>,
    React.ComponentPropsWithoutRef<typeof ProgressPrimitive.Root> & { indicatorClassName?: string }
>(({ className, value, indicatorClassName, ...props }, ref) => (
    <ProgressPrimitive.Root
        ref={ref}
        className={cn('relative h-1.5 w-full overflow-hidden rounded-full bg-elevated', className)}
        {...props}
    >
        <ProgressPrimitive.Indicator
            className={cn('h-full w-full flex-1 bg-accent transition-transform', indicatorClassName)}
            style={{ transform: `translateX(-${100 - (value ?? 0)}%)` }}
        />
    </ProgressPrimitive.Root>
));
Progress.displayName = ProgressPrimitive.Root.displayName;
