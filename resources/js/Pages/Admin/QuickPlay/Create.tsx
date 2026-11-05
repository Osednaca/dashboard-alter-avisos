import { UploadDropzone } from '@/Components/app/UploadDropzone';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Image as ImageIcon,
    Layers,
    MonitorPlay,
    Search,
    Send,
    Sparkles,
    Zap,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { FormField } from '@/Components/app/FormField';
import { MediaThumbnail } from '@/Components/app/MediaThumbnail';
import { PageHeader } from '@/Components/app/PageHeader';
import { QuickPlayPreview } from '@/Components/app/QuickPlayPreview';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { usePermissions } from '@/Hooks/usePermissions';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { BusinessLayout } from '@/Layouts/BusinessLayout';
import type { MediaEntity } from '@/Types';
import { formatNumber } from '@/Utils/format';
import { cn } from '@/Utils/cn';

interface DeviceOption {
    id: number;
    name: string;
    business: string | null;
    city: string | null;
    status: { value: string; label: string; tone?: string };
    is_online: boolean;
}

interface BusinessOption {
    id: number;
    name: string;
    devices_count: number;
}

interface LocationOption {
    id: number;
    name: string;
    city: string;
    business: string | null;
    devices_count: number;
}

interface Options {
    media: MediaEntity[];
    devices: DeviceOption[];
    businesses: BusinessOption[];
    locations: LocationOption[];
    displayModes: Array<{ value: string; label: string; description: string }>;
    scopes: Array<{ value: string; label: string }>;
    counts: { devices: number; businesses: number; locations: number };
}

const modeIcons: Record<string, typeof Layers> = {
    advertising: Layers,
    business: MonitorPlay,
    fullscreen: Sparkles,
};

export default function QuickPlayCreate({ options, portal = 'admin' }: { options: Options; portal?: 'admin' | 'business' }) {
    const { can } = usePermissions();
    const PageLayout = portal === 'business' ? BusinessLayout : AdminLayout;
    const basePath = `/${portal}/quick-play`;


    const form = useForm({
        media_asset_id: null as number | null,
        display_mode: portal === 'business' ? 'business' : 'advertising',
        scope: 'devices',
        duration: 10,
        natural_duration: false,
        device_ids: [] as number[],
        business_ids: [] as number[],
        location_ids: [] as number[],
    });

    const { data, setData, errors } = form;
    const targetError = (errors as Record<string, string | undefined>).targets;
    const [deviceSearch, setDeviceSearch] = useState('');


    const selectedMedia = useMemo(
        () => options.media.find((media) => media.id === data.media_asset_id) ?? null,
        [options.media, data.media_asset_id],
    );

    const isVideo = selectedMedia?.type?.value === 'video';

    const targetCount = useMemo(() => {
        switch (data.scope) {
            case 'devices':
                return data.device_ids.length;
            case 'businesses':
                return options.businesses
                    .filter((business) => data.business_ids.includes(business.id))
                    .reduce((total, business) => total + business.devices_count, 0);
            case 'locations':
                return options.locations
                    .filter((location) => data.location_ids.includes(location.id))
                    .reduce((total, location) => total + location.devices_count, 0);
            default:
                return options.counts.devices;
        }
    }, [data.scope, data.device_ids, data.business_ids, data.location_ids, options]);

    const filteredDevices = useMemo(() => {
        const term = deviceSearch.trim().toLowerCase();
        if (!term) return options.devices;
        return options.devices.filter(
            (device) =>
                device.name.toLowerCase().includes(term) ||
                (device.business ?? '').toLowerCase().includes(term) ||
                (device.city ?? '').toLowerCase().includes(term),
        );
    }, [options.devices, deviceSearch]);

    const effectiveDuration = isVideo && data.natural_duration ? null : data.duration;

    const canSubmit =
        !!selectedMedia &&
        targetCount > 0 &&
        (isVideo && data.natural_duration ? true : (data.duration ?? 0) >= 3);

    useEffect(() => {
        // When switching to a video, default to its natural duration.
        if (isVideo) {
            setData('natural_duration', true);
        } else {
            setData('natural_duration', false);
            if (!data.duration || data.duration < 3) setData('duration', 10);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isVideo]);

    const toggle = (field: 'device_ids' | 'business_ids' | 'location_ids', id: number) => {
        const current = data[field];
        setData(field, current.includes(id) ? current.filter((value) => value !== id) : [...current, id]);
    };

    const submit = () => {
        form.transform((payload) => ({
            ...payload,
            duration: payload.display_mode && payload.natural_duration && isVideo ? null : payload.duration,
        }));
        form.post(basePath);
    };

    return (
        <PageLayout>
            <Head title="Nueva reproducción inmediata" />

            <PageHeader
                eyebrow="Reproducción inmediata"
                title="Enviar contenido ahora"
                description="Sube o elige una creatividad y envíala al instante a las pantallas seleccionadas."
                actions={
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={basePath}>
                            <ArrowLeft className="size-4" />
                            Volver al historial
                        </Link>
                    </Button>
                }
            />

            <div className="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-12">
                <div className="space-y-4 xl:col-span-7">
                    {/* 1. Contenido */}
                    <Card>
                        <CardHeader>
                            <CardTitle>1 · Contenido</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {can(portal === 'business' ? 'business.media.upload' : 'creatives.manage') && <div className="mb-4"><UploadDropzone action={portal === 'business' ? '/business/library' : '/admin/creatives'} /></div>}
                            {options.media.length === 0 ? (
                                <p className="rounded-control border border-dashed border-line px-4 py-8 text-center text-xs text-faint">
                                    No hay creatividades disponibles. Sube una imagen o video.
                                </p>
                            ) : (
                                <div className="grid max-h-72 grid-cols-2 gap-3 overflow-y-auto pr-1 sm:grid-cols-4">
                                    {options.media.map((media) => {
                                        const selected = media.id === data.media_asset_id;
                                        return (
                                            <button
                                                key={media.id}
                                                type="button"
                                                onClick={() => setData('media_asset_id', media.id)}
                                                className={cn(
                                                    'relative space-y-1.5 rounded-control border p-1.5 text-left transition-colors',
                                                    selected ? 'border-accent/60 bg-accent/10' : 'border-line hover:border-line-strong',
                                                )}
                                            >
                                                <MediaThumbnail media={media} />
                                                <p className="truncate text-[11px] text-fg">{media.filename}</p>
                                                <p className="text-[10px] text-faint">
                                                    {media.type.label}
                                                    {media.formatted_duration ? ` · ${media.formatted_duration}` : ''}
                                                </p>
                                                {selected ? (
                                                    <span className="absolute right-2 top-2 flex size-4 items-center justify-center rounded-full bg-accent text-[#20170a]">
                                                        <Check className="size-3" strokeWidth={3} />
                                                    </span>
                                                ) : null}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                            {errors.media_asset_id ? <p className="mt-2 text-xs text-danger">{errors.media_asset_id}</p> : null}
                        </CardContent>
                    </Card>

                    {/* 2. Destino */}
                    <Card>
                        <CardHeader>
                            <CardTitle>2 · Destino</CardTitle>
                            <span className="text-xs text-muted">
                                <span className="metric text-fg">{formatNumber(targetCount)}</span> pantallas
                            </span>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {portal === 'business' ? (
                                <p className="text-sm text-muted">Selecciona las pantallas de tu negocio donde quieres reproducir el contenido.</p>
                            ) : <Tabs value={data.scope} onValueChange={(value) => setData('scope', value)}>
                                <TabsList className="grid w-full grid-cols-2 sm:flex">
                                    {options.scopes.map((scope) => (
                                        <TabsTrigger key={scope.value} value={scope.value} className="min-w-0 flex-1 justify-center whitespace-normal text-center">
                                            {scope.label}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                            </Tabs>}

                            {data.scope === 'devices' ? (
                                <div className="space-y-2">
                                    <div className="relative">
                                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-faint" />
                                        <Input
                                            value={deviceSearch}
                                            onChange={(event) => setDeviceSearch(event.target.value)}
                                            placeholder={portal === 'business' ? 'Buscar pantalla o ciudad…' : 'Buscar pantalla, negocio o ciudad…'}
                                            className="pl-9"
                                        />
                                    </div>
                                    <SelectionList
                                        items={filteredDevices.map((device) => ({
                                            id: device.id,
                                            title: device.name,
                                            subtitle: [device.business, device.city].filter(Boolean).join(' · '),
                                            online: device.is_online,
                                        }))}
                                        selected={data.device_ids}
                                        onToggle={(id) => toggle('device_ids', id)}
                                    />
                                </div>
                            ) : null}

                            {data.scope === 'businesses' ? (
                                <SelectionList
                                    items={options.businesses.map((business) => ({
                                        id: business.id,
                                        title: business.name,
                                        subtitle: `${business.devices_count} pantallas`,
                                        online: true,
                                    }))}
                                    selected={data.business_ids}
                                    onToggle={(id) => toggle('business_ids', id)}
                                />
                            ) : null}

                            {data.scope === 'locations' ? (
                                <SelectionList
                                    items={options.locations.map((location) => ({
                                        id: location.id,
                                        title: location.name,
                                        subtitle: [location.business, location.city].filter(Boolean).join(' · '),
                                        online: true,
                                    }))}
                                    selected={data.location_ids}
                                    onToggle={(id) => toggle('location_ids', id)}
                                />
                            ) : null}

                            {data.scope === 'all' ? (
                                <p className="rounded-control border border-info/25 bg-info/10 px-4 py-3 text-xs text-info">
                                    Se enviará a las {formatNumber(options.counts.devices)} pantallas activas de la red.
                                </p>
                            ) : null}

                            {targetError ? <p className="text-xs text-danger">{targetError}</p> : null}
                            {errors.device_ids ? <p className="text-xs text-danger">{errors.device_ids}</p> : null}
                            {errors.scope ? <p className="text-xs text-danger">{errors.scope}</p> : null}
                        </CardContent>
                    </Card>

                    {/* 3. Modo de visualización */}
                    <Card>
                        <CardHeader>
                            <CardTitle>3 · Modo de visualización</CardTitle>
                        </CardHeader>
                        <CardContent className={portal === 'business' ? 'space-y-2' : 'grid grid-cols-1 gap-3 sm:grid-cols-3'}>
                            {portal === 'business' ? (
                                <div className="rounded-control border border-accent/60 bg-accent/10 p-3">
                                    <MonitorPlay className="mb-2 size-4 text-accent" />
                                    <p className="text-xs font-medium text-fg">Zona del negocio</p>
                                    <p className="mt-1 text-xs text-muted">El contenido se reproduce dentro del espacio de tu negocio en el layout de la pantalla.</p>
                                </div>
                            ) : options.displayModes.map((mode) => {
                                const Icon = modeIcons[mode.value] ?? Layers;
                                const selected = data.display_mode === mode.value;
                                return (
                                    <button
                                        key={mode.value}
                                        type="button"
                                        onClick={() => setData('display_mode', mode.value)}
                                        className={cn(
                                            'rounded-control border p-3 text-left transition-colors',
                                            selected ? 'border-accent/60 bg-accent/10' : 'border-line hover:border-line-strong',
                                        )}
                                    >
                                        <Icon className={cn('mb-2 size-4', selected ? 'text-accent' : 'text-faint')} />
                                        <p className="text-xs font-medium text-fg">{mode.label}</p>
                                        <p className="mt-1 text-[11px] leading-relaxed text-muted">{mode.description}</p>
                                    </button>
                                );
                            })}
                            {errors.display_mode ? <p className="text-xs text-danger">{errors.display_mode}</p> : null}
                        </CardContent>
                    </Card>

                    {/* 4. Duración */}
                    <Card>
                        <CardHeader>
                            <CardTitle>4 · Duración</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {isVideo ? (
                                <>
                                    <div className="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            onClick={() => setData('natural_duration', true)}
                                            className={cn(
                                                'rounded-control border px-3 py-1.5 text-xs transition-colors',
                                                data.natural_duration ? 'border-accent/50 bg-accent/10 text-accent' : 'border-line text-muted',
                                            )}
                                        >
                                            Duración natural {selectedMedia?.formatted_duration ? `(${selectedMedia.formatted_duration})` : ''}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setData('natural_duration', false)}
                                            className={cn(
                                                'rounded-control border px-3 py-1.5 text-xs transition-colors',
                                                !data.natural_duration ? 'border-accent/50 bg-accent/10 text-accent' : 'border-line text-muted',
                                            )}
                                        >
                                            Duración personalizada
                                        </button>
                                    </div>
                                    {!data.natural_duration ? (
                                        <FormField label="Segundos en pantalla" error={errors.duration}>
                                            <Input
                                                type="number"
                                                min={3}
                                                max={3600}
                                                value={data.duration}
                                                onChange={(event) => setData('duration', Number(event.target.value))}
                                                className="w-40"
                                            />
                                        </FormField>
                                    ) : null}
                                </>
                            ) : (
                                <FormField label="Segundos en pantalla" error={errors.duration} hint="Tiempo durante el cual se mostrará la imagen.">
                                    <Input
                                        type="number"
                                        min={3}
                                        max={3600}
                                        value={data.duration}
                                        onChange={(event) => setData('duration', Number(event.target.value))}
                                        className="w-40"
                                    />
                                </FormField>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Preview + resumen */}
                <div className="xl:col-span-5">
                    <div className="space-y-4 xl:sticky xl:top-24">
                        <Card>
                            <CardHeader>
                                <CardTitle>Vista previa</CardTitle>
                                <Badge tone="accent">
                                    <Zap className="size-3" />
                                    Sin campaña
                                </Badge>
                            </CardHeader>
                            <CardContent>
                                <QuickPlayPreview media={selectedMedia} displayMode={data.display_mode} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Resumen de envío</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <SummaryRow label="Contenido" value={selectedMedia?.filename ?? 'Sin seleccionar'} />
                                <SummaryRow
                                    label="Modo"
                                    value={options.displayModes.find((mode) => mode.value === data.display_mode)?.label ?? '—'}
                                />
                                <SummaryRow
                                    label="Destino"
                                    value={options.scopes.find((scope) => scope.value === data.scope)?.label ?? '—'}
                                />
                                <SummaryRow
                                    label="Duración"
                                    value={isVideo && data.natural_duration ? 'Natural del video' : `${effectiveDuration ?? data.duration} s`}
                                />
                                <SummaryRow label="Pantallas" value={formatNumber(targetCount)} emphasize />

                                <div className="rounded-control border border-line bg-surface px-3 py-2 text-[11px] leading-relaxed text-faint">
                                    Al finalizar, cada pantalla reanuda su contenido programado. Las pantallas
                                    desconectadas se marcarán como no entregadas.
                                </div>

                                <Button variant="primary" className="w-full" onClick={submit} disabled={!canSubmit || form.processing}>
                                    {form.processing ? <ImageIcon className="size-4 animate-pulse" /> : <Send className="size-4" />}
                                    Reproducir ahora
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </PageLayout>
    );
}

function SummaryRow({ label, value, emphasize = false }: { label: string; value: string; emphasize?: boolean }) {
    return (
        <div className="flex items-center justify-between gap-4 border-b border-line/60 pb-2 last:border-0">
            <span className="text-xs text-muted">{label}</span>
            <span className={cn('truncate text-right', emphasize ? 'metric text-base text-fg' : 'text-fg')}>{value}</span>
        </div>
    );
}

function SelectionList({
    items,
    selected,
    onToggle,
}: {
    items: Array<{ id: number; title: string; subtitle: string; online: boolean }>;
    selected: number[];
    onToggle: (id: number) => void;
}) {
    if (items.length === 0) {
        return (
            <p className="rounded-control border border-dashed border-line px-4 py-6 text-center text-xs text-faint">
                Sin resultados.
            </p>
        );
    }

    return (
        <div className="max-h-64 space-y-1.5 overflow-y-auto pr-1">
            {items.map((item) => {
                const checked = selected.includes(item.id);
                return (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => onToggle(item.id)}
                        className={cn(
                            'flex w-full items-center gap-3 rounded-control border px-3 py-2 text-left transition-colors',
                            checked ? 'border-accent/50 bg-accent/10' : 'border-line bg-surface hover:border-line-strong',
                        )}
                    >
                        <span
                            className={cn(
                                'flex size-4 shrink-0 items-center justify-center rounded-[5px] border',
                                checked ? 'border-accent bg-accent text-[#20170a]' : 'border-line-strong',
                            )}
                        >
                            {checked ? <Check className="size-3" strokeWidth={3} /> : null}
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-xs text-fg">{item.title}</span>
                            <span className="block truncate text-[11px] text-faint">{item.subtitle || '—'}</span>
                        </span>
                        {item.online ? (
                            <span className="size-2 shrink-0 rounded-full bg-positive" title="En línea" />
                        ) : (
                            <span className="size-2 shrink-0 rounded-full bg-danger" title="Desconectada" />
                        )}
                    </button>
                );
            })}
        </div>
    );
}
