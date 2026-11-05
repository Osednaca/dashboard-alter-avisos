import { Search } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { Input } from '@/Components/ui/input';
import { cn } from '@/Utils/cn';

export function FilterBar({
    search,
    onSearch,
    searchPlaceholder = 'Buscar…',
    children,
    className,
}: {
    search?: string;
    onSearch?: (value: string) => void;
    searchPlaceholder?: string;
    children?: ReactNode;
    className?: string;
}) {
    const [value, setValue] = useState(search ?? '');

    useEffect(() => {
        setValue(search ?? '');
    }, [search]);

    useEffect(() => {
        if (!onSearch) return;
        const timeout = setTimeout(() => {
            if (value !== (search ?? '')) onSearch(value);
        }, 350);
        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    return (
        <div className={cn('flex flex-col gap-3 lg:flex-row lg:items-center', className)}>
            {onSearch ? (
                <div className="relative w-full lg:max-w-xs lg:flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-faint" />
                    <Input
                        value={value}
                        onChange={(event) => setValue(event.target.value)}
                        placeholder={searchPlaceholder}
                        className="pl-9"
                        aria-label={searchPlaceholder}
                    />
                </div>
            ) : null}
            {children ? (
                <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center lg:w-auto">
                    {children}
                </div>
            ) : null}
        </div>
    );
}
