import { UploadDropzone } from '@/Components/app/UploadDropzone';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Image as ImageIcon,
    Search,
    Send,
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
import type { MediaEntity } from '@/Types';
import { formatNumber } from '@/Utils/format';
import { countCityDevices as countTargetDevices } from '@/Utils/citySelection';
import { cn } from '@/Utils/cn';

interface DeviceOption {
    id: number;
    name: string;
    city: string | null;
    status: { value: string; label: string; tone?: string };
    is_online: boolean;
}

interface TargetGroupOption {
    id: number;
    name: string;
    device_ids: number[];
}

interface Options {
    media: MediaEntity[];
    devices: DeviceOption[];
    cities: TargetGroupOption[];
    businesses: TargetGroupOption[];
    displayModes: Array<{ value: string; label: string; description: string }>;
    scopes: Array<{ value: string; label: string }>;
    counts: { devices: number };
}

export default function QuickPlayCreate({ options }: { options: Options }) {
    const { can } = usePermissions();
    const basePath = '/admin/quick-play';


    const form = useForm({
        media_asset_id: null as number | null,
        display_mode: 'fullscreen',
        scope: 'devices',
        duration: 10,
        natural_duration: false,
        device_ids: [] as number[],
        city_ids: [] as number[],
        business_ids: [] as number[],
    });

    const { data, setData, errors } = form;
    const targetError = (errors as Record<string, string | undefined>).targets;
    const [deviceSearch, setDeviceSearch] = useState('');


    const selectedMedia = useMemo(
        () => options.media.find((media) => media.id === data.media_asset_id) ?? null,
        [options.media, data.media_asset_id],
    );

    const isVideo = selectedMedia?.type?.value === 'video';
    const eligibleDeviceIds = useMemo(() => options.devices.map((device) => device.id), [options.devices]);
    const eligibleDeviceIdSet = useMemo(() => new Set(eligibleDeviceIds), [eligibleDeviceIds]);

    const targetCount = useMemo(() => {
        switch (data.scope) {
            case 'devices':
                return data.device_ids.length;
            case 'cities': {
                return countTargetDevices(options.cities, data.city_ids, eligibleDeviceIds);
            }
            case 'businesses': {
                return countTargetDevices(options.businesses, data.business_ids, eligibleDeviceIds);
            }
            default:
                return options.counts.devices;
        }
    }, [data.scope, data.device_ids, data.city_ids, data.business_ids, options, eligibleDeviceIds]);

    const filteredDevices = useMemo(() => {
        const term = deviceSearch.trim().toLowerCase();
        if (!term) return options.devices;
        return options.devices.filter(
            (device) =>
                device.name.toLowerCase().includes(term) ||
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

    const toggle = (field: 'device_ids' | 'city_ids' | 'business_ids', id: number) => {
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
        <AdminLayout>
            <Head title="Nueva reproducción inmediata" />

            <PageHeader
                eyebrow="Reproducción inmediata"
                title="Enviar contenido ahora"
                description="Sube o elige una creatividad y envíala al instante a los avisos LED seleccionados."
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
                            {can('creatives.manage') && <div className="mb-4"><UploadDropzone action="/admin/creatives" /></div>}
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
                                <span className="metric text-fg">{formatNumber(targetCount)}</span> avisos LED
                            </span>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Tabs value={data.scope} onValueChange={(value) => setData('scope', value)}>
                                <TabsList className="grid w-full grid-cols-2 sm:flex">
                                    {options.scopes.filter((scope) => ['devices', 'cities', 'businesses', 'all'].includes(scope.value)).map((scope) => (
                                        <TabsTrigger key={scope.value} value={scope.value} className="min-w-0 flex-1 justify-center whitespace-normal text-center">
                                            {scope.label}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                            </Tabs>

                            {data.scope === 'devices' ? (
                                <div className="space-y-2">
                                    <div className="relative">
                                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-faint" />
                                        <Input
                                            value={deviceSearch}
                                            onChange={(event) => setDeviceSearch(event.target.value)}
                                            placeholder="Buscar aviso LED o ciudad…"
                                            className="pl-9"
                                        />
                                    </div>
                                    <SelectionList
                                        items={filteredDevices.map((device) => ({
                                            id: device.id,
                                            title: device.name,
                                            subtitle: device.city ?? '',
                                            online: device.is_online,
                                        }))}
                                        selected={data.device_ids}
                                        onToggle={(id) => toggle('device_ids', id)}
                                    />
                                </div>
                            ) : null}

                            {data.scope === 'cities' ? (
                                <SelectionList
                                    items={options.cities.map((city) => ({
                                        id: city.id,
                                        title: city.name,
                                        subtitle: `${city.device_ids.length} avisos LED`,
                                        online: true,
                                    }))}
                                    selected={data.city_ids}
                                    onToggle={(id) => toggle('city_ids', id)}
                                />
                            ) : null}

                            {data.scope === 'businesses' ? (
                                <SelectionList
                                    items={options.businesses.map((business) => ({
                                        id: business.id,
                                        title: business.name,
                                        subtitle: `${new Set(business.device_ids.filter((id) => eligibleDeviceIdSet.has(id))).size} avisos LED`,
                                        online: true,
                                    }))}
                                    selected={data.business_ids}
                                    onToggle={(id) => toggle('business_ids', id)}
                                />
                            ) : null}

                            {data.scope === 'all' ? (
                                <p className="rounded-control border border-info/25 bg-info/10 px-4 py-3 text-xs text-info">
                                    Se enviará a los {formatNumber(options.counts.devices)} avisos LED activos de la red.
                                </p>
                            ) : null}

                            {targetError ? <p className="text-xs text-danger">{targetError}</p> : null}
                            {errors.device_ids ? <p className="text-xs text-danger">{errors.device_ids}</p> : null}
                            {errors.city_ids ? <p className="text-xs text-danger">{errors.city_ids}</p> : null}
                            {errors.business_ids ? <p className="text-xs text-danger">{errors.business_ids}</p> : null}
                            {errors.scope ? <p className="text-xs text-danger">{errors.scope}</p> : null}
                        </CardContent>
                    </Card>

                    {/* 3. Duración */}
                    <Card>
                        <CardHeader>
                            <CardTitle>3 · Duración</CardTitle>
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
                                    label="Destino"
                                    value={options.scopes.find((scope) => scope.value === data.scope)?.label ?? '—'}
                                />
                                <SummaryRow
                                    label="Duración"
                                    value={isVideo && data.natural_duration ? 'Natural del video' : `${effectiveDuration ?? data.duration} s`}
                                />
                                <SummaryRow label="Avisos LED" value={formatNumber(targetCount)} emphasize />

                                <div className="rounded-control border border-line bg-surface px-3 py-2 text-[11px] leading-relaxed text-faint">
                                    Al finalizar, cada aviso LED reanuda su contenido programado. Los avisos LED
                                    desconectados se marcarán como no entregados.
                                </div>

                                {errors.display_mode ? <p className="text-xs text-danger">{errors.display_mode}</p> : null}
                                <Button variant="primary" className="w-full" onClick={submit} disabled={!canSubmit || form.processing}>
                                    {form.processing ? <ImageIcon className="size-4 animate-pulse" /> : <Send className="size-4" />}
                                    Reproducir ahora
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AdminLayout>
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
