import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

export function ChartCard({
    title,
    subtitle,
    actions,
    children,
    className,
    contentClassName,
}: {
    title: string;
    subtitle?: string;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
}) {
    return (
        <Card className={className}>
            <CardHeader>
                <div>
                    <CardTitle>{title}</CardTitle>
                    {subtitle ? <p className="mt-0.5 text-xs text-muted">{subtitle}</p> : null}
                </div>
                {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
            </CardHeader>
            <CardContent className={contentClassName}>{children}</CardContent>
        </Card>
    );
}
