import * as React from 'react';
import { cn } from '@/Utils/cn';

export const Input = React.forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement>>(
    ({ className, type, ...props }, ref) => (
        <input
            ref={ref}
            type={type}
            className={cn(
                'flex h-10 w-full rounded-control border border-line bg-inset px-3 py-2 text-sm text-fg transition-colors placeholder:text-faint focus-visible:border-line-strong focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    ),
);
Input.displayName = 'Input';

export const Textarea = React.forwardRef<
    HTMLTextAreaElement,
    React.TextareaHTMLAttributes<HTMLTextAreaElement>
>(({ className, ...props }, ref) => (
    <textarea
        ref={ref}
        className={cn(
            'flex min-h-20 w-full rounded-control border border-line bg-inset px-3 py-2 text-sm text-fg transition-colors placeholder:text-faint focus-visible:border-line-strong focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50',
            className,
        )}
        {...props}
    />
));
Textarea.displayName = 'Textarea';
