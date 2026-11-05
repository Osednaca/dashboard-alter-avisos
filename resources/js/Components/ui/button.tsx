import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';
import { cn } from '@/Utils/cn';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-control text-sm font-medium transition-colors focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                primary: 'bg-accent text-[#20170a] font-semibold hover:bg-accent-strong',
                secondary: 'bg-elevated text-fg hover:bg-[#183049] border border-line',
                outline: 'border border-line-strong bg-transparent text-fg hover:bg-elevated',
                ghost: 'text-muted hover:bg-elevated hover:text-fg',
                danger: 'bg-danger/15 text-danger border border-danger/30 hover:bg-danger/25',
                success: 'bg-positive/15 text-positive border border-positive/30 hover:bg-positive/25',
            },
            size: {
                // Slightly taller on phones for comfortable touch targets.
                sm: 'min-h-11 px-3 text-xs sm:min-h-0 sm:h-8',
                md: 'min-h-11 px-4 sm:min-h-0 sm:h-10',
                lg: 'h-11 px-6 text-base',
                icon: 'h-11 w-11 sm:h-9 sm:w-9',
                'icon-sm': 'h-11 w-11 sm:h-8 sm:w-8',
            },
        },
        defaultVariants: {
            variant: 'secondary',
            size: 'md',
        },
    },
);

export interface ButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    asChild?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, asChild = false, ...props }, ref) => {
        const Comp = asChild ? Slot : 'button';
        return (
            <Comp
                ref={ref}
                className={cn(buttonVariants({ variant, size }), className)}
                {...props}
            />
        );
    },
);
Button.displayName = 'Button';

export { Button, buttonVariants };
