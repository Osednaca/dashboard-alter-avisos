import { MoreVertical, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

export interface ActionItem {
    label: string;
    icon?: LucideIcon;
    onSelect: () => void;
    variant?: 'default' | 'danger';
    hidden?: boolean;
    separatorBefore?: boolean;
}

export function ActionMenu({
    items,
    trigger,
    align = 'end',
}: {
    items: ActionItem[];
    trigger?: ReactNode;
    align?: 'start' | 'center' | 'end';
}) {
    const visible = items.filter((item) => !item.hidden);
    if (visible.length === 0) return null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                {trigger ?? (
                    <Button variant="ghost" size="icon-sm" aria-label="Acciones">
                        <MoreVertical className="size-4" />
                    </Button>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent align={align}>
                {visible.map((item) => (
                    <div key={item.label}>
                        {item.separatorBefore ? <DropdownMenuSeparator /> : null}
                        <DropdownMenuItem variant={item.variant} onSelect={item.onSelect}>
                            {item.icon ? <item.icon className="size-4" /> : null}
                            {item.label}
                        </DropdownMenuItem>
                    </div>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
