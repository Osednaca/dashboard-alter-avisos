import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/Types';
import { cn } from '@/Utils/cn';

export function BrandLogo({ className, decorative = false }: { className?: string; decorative?: boolean }) {
    const { app } = usePage<PageProps>().props;

    return (
        <img
            src={app.logo_url}
            alt={decorative ? '' : app.name}
            width={1064}
            height={1064}
            className={cn('size-9 shrink-0 object-contain', className)}
        />
    );
}
