const numberFormatter = new Intl.NumberFormat('es-CO');

export function formatNumber(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return numberFormatter.format(value);
}

export function formatCompact(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    if (Math.abs(value) >= 1_000_000) return `${(value / 1_000_000).toFixed(1).replace('.0', '')} M`;
    if (Math.abs(value) >= 1_000) return `${(value / 1_000).toFixed(1).replace('.0', '')} K`;
    return numberFormatter.format(value);
}

export function formatBytes(bytes: number | null | undefined): string {
    if (bytes === null || bytes === undefined) return '—';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let index = 0;
    while (value >= 1024 && index < units.length - 1) {
        value /= 1024;
        index += 1;
    }
    return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

export function formatDuration(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined) return '—';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) return `${hours} h ${minutes} min`;
    return `${minutes} min`;
}

export function formatPercent(value: number | null | undefined, digits = 1): string {
    if (value === null || value === undefined) return '—';
    return `${value.toFixed(digits)}%`;
}

export function formatDate(value: string | null | undefined): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('es-CO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('es-CO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatTime(value: string | null | undefined): string {
    if (!value) return '—';
    return value.slice(0, 5);
}

export function formatRelative(value: string | null | undefined): string {
    if (!value) return '—';
    const date = new Date(value).getTime();
    const diff = Date.now() - date;
    const minutes = Math.round(diff / 60000);

    if (minutes < 1) return 'ahora';
    if (minutes < 60) return `hace ${minutes} min`;

    const hours = Math.round(minutes / 60);
    if (hours < 24) return `hace ${hours} h`;

    const days = Math.round(hours / 24);
    if (days < 30) return `hace ${days} d`;

    return formatDate(value);
}

export function initialsFrom(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}
