import type { EnumValue, Tone } from '@/Types';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/Utils/cn';

export function StatusBadge({
    value,
    className,
    dot = true,
}: {
    value: EnumValue | null | undefined;
    className?: string;
    dot?: boolean;
}) {
    if (!value) return <span className="text-faint">—</span>;

    return (
        <Badge tone={(value.tone as Tone) ?? 'neutral'} dot={dot} className={cn(className)}>
            {value.label}
        </Badge>
    );
}
