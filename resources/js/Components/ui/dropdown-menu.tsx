import * as DropdownMenuPrimitive from '@radix-ui/react-dropdown-menu';
import { Check } from 'lucide-react';
import * as React from 'react';
import { cn } from '@/Utils/cn';

export const DropdownMenu = DropdownMenuPrimitive.Root;
export const DropdownMenuTrigger = DropdownMenuPrimitive.Trigger;
export const DropdownMenuGroup = DropdownMenuPrimitive.Group;
export const DropdownMenuSub = DropdownMenuPrimitive.Sub;

export const DropdownMenuContent = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.Content>,
    React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Content>
>(({ className, sideOffset = 6, ...props }, ref) => (
    <DropdownMenuPrimitive.Portal>
        <DropdownMenuPrimitive.Content
            ref={ref}
            sideOffset={sideOffset}
            className={cn(
                'z-50 min-w-44 overflow-hidden rounded-control border border-line bg-elevated p-1 text-sm text-fg shadow-pop',
                className,
            )}
            {...props}
        />
    </DropdownMenuPrimitive.Portal>
));
DropdownMenuContent.displayName = DropdownMenuPrimitive.Content.displayName;

const itemBase =
    'relative flex cursor-pointer select-none items-center gap-2 rounded-[7px] px-2.5 py-2 text-sm outline-none transition-colors focus:bg-card data-[disabled]:pointer-events-none data-[disabled]:opacity-50';

export const DropdownMenuItem = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.Item>,
    React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Item> & {
        variant?: 'default' | 'danger';
    }
>(({ className, variant = 'default', ...props }, ref) => (
    <DropdownMenuPrimitive.Item
        ref={ref}
        className={cn(
            itemBase,
            variant === 'danger'
                ? 'text-danger focus:bg-danger/15'
                : 'text-fg',
            className,
        )}
        {...props}
    />
));
DropdownMenuItem.displayName = DropdownMenuPrimitive.Item.displayName;

export const DropdownMenuCheckboxItem = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.CheckboxItem>,
    React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.CheckboxItem>
>(({ className, children, checked, ...props }, ref) => (
    <DropdownMenuPrimitive.CheckboxItem
        ref={ref}
        checked={checked}
        className={cn(itemBase, 'pr-8', className)}
        {...props}
    >
        {children}
        <span className="absolute right-2.5">
            <DropdownMenuPrimitive.ItemIndicator>
                <Check className="size-3.5 text-accent" />
            </DropdownMenuPrimitive.ItemIndicator>
        </span>
    </DropdownMenuPrimitive.CheckboxItem>
));
DropdownMenuCheckboxItem.displayName = DropdownMenuPrimitive.CheckboxItem.displayName;

export function DropdownMenuLabel({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-faint', className)}
            {...props}
        />
    );
}

export function DropdownMenuSeparator({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('my-1 h-px bg-line', className)} {...props} />;
}
