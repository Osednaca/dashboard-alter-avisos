import { Head, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    Check,
    Image as ImageIcon,
    Rocket,
    Save,
    Target,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { FormField } from '@/Components/app/FormField';
import { MediaThumbnail } from '@/Components/app/MediaThumbnail';
import { LiveStreamDialog } from '@/Components/app/LiveStreamDialog';
import { LiveCampaignFields } from '@/Components/app/LiveCampaignFields';
import { usePermissions } from '@/Hooks/usePermissions';
import { PageHeader } from '@/Components/app/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input, Textarea } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { MediaEntity, Option } from '@/Types';
import { initialCampaignValues, type CreativeInput, type FormCampaign, type TargetInput } from '@/Utils/campaignForm';
import { formatNumber } from '@/Utils/format';
import { cn } from '@/Utils/cn';

interface Options {
    advertisers: Array<{ id: number; name: string; status: { value: string; label: string } }>;
    creatives: MediaEntity[];
    cities: Array<{ id: number; name: string; state: string; country: string }>;
    categories: Option[];
    businesses: Array<{ id: number; name: string }>;
    locations: Array<{ id: number; name: string; city: string; business: string | null }>;
    devices: Array<{ id: number; name: string; business: string | null }>;
    counts: { screens: number; businesses: number; locations: number; cities: number };
}

const steps = [
    { id: 0, label: 'Información' },
    { id: 1, label: 'Creatividades' },
    { id: 2, label: 'Segmentación' },
    { id: 3, label: 'Programación' },
    { id: 4, label: 'Reglas' },
    { id: 5, label: 'Revisión' },
];

const targetTypes: Option[] = [
    { value: 'city', label: 'Ubicación (ciudad)' },
    { value: 'business_category', label: 'Categoría' },
    { value: 'business', label: 'Negocio' },
    { value: 'location', label: 'Sucursal' },
    { value: 'device', label: 'Pantalla' },
    { value: 'state', label: 'Departamento' },
    { value: 'country', label: 'País' },
];

const weekDays = [
    { value: 1, label: 'L' },
    { value: 2, label: 'M' },
    { value: 3, label: 'M' },
    { value: 4, label: 'J' },
    { value: 5, label: 'V' },
    { value: 6, label: 'S' },
    { value: 7, label: 'D' },
];

export default function CampaignForm({ campaign, options, selectedMedia = [] }: { campaign: FormCampaign | null; options: Options; selectedMedia?: MediaEntity[] }) {
    const { can } = usePermissions();
    const [addedSources, setAddedSources] = useState<MediaEntity[]>([]);
    const creativeOptions = [...addedSources, ...options.creatives];
    const [step, setStep] = useState(0);
    const [summary, setSummary] = useState<{ screens: number; businesses: number; locations: number; cities: number } | null>(
        campaign ? { screens: campaign.target_screen_count, businesses: 0, locations: 0, cities: 0 } : null,
    );

    const form = useForm(initialCampaignValues(campaign, selectedMedia));

    const { data, setData, errors } = form;

    const addCreative = (media: MediaEntity) => {
        if (data.creatives.some((creative) => creative.media_asset_id === media.id)) return;
        setData('creatives', [...data.creatives, { media_asset_id: media.id, duration: media.duration ?? 10, weight: 10,
            ...(media.type.value === 'live_stream' ? {configuration: {display_mode: 'advertising_zone', audio: false, starts_at: '', ends_at: '', fallback_media_id: null, size_acknowledged: false}} : {}) }]);
    };

    const removeCreative = (id: number | '') => {
        setData('creatives', data.creatives.filter((creative) => creative.media_asset_id !== id));
    };

    const updateCreative = (id: number | '', patch: Partial<CreativeInput>) => {
        setData(
            'creatives',
            data.creatives.map((creative) => (creative.media_asset_id === id ? { ...creative, ...patch } : creative)),
        );
    };

    const addTarget = (target: TargetInput) => {
        setData('targets', [...data.targets, target]);
    };

    const refreshSummary = async () => {
        if (data.targets.length === 0) {
            setSummary(null);
            return;
        }

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        try {
            const response = await fetch('/admin/campaigns/preview-targets', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ targets: data.targets }),
            });

            if (response.ok) {
                const payload = (await response.json()) as { summary: typeof summary };
                setSummary(payload.summary);
            }
        } catch {
            /* preview is best-effort */
        }
    };

    useEffect(() => {
        if (step === 5) void refreshSummary();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [step]);

    const canAdvance = () => {
        if (step === 0) return data.name.length > 2 && !!data.advertiser_id && !!data.starts_at && !!data.ends_at;
        if (step === 1) return data.creatives.length > 0;
        if (step === 2) return data.targets.length > 0;
        return true;
    };

    const submit = (publish: boolean) => {
        setData('publish', publish);
        const payload = { ...data, publish };
        form.transform(() => payload);
        if (campaign) {
            form.put(`/admin/campaigns/${campaign.id}`, { preserveScroll: true });
        } else {
            form.post('/admin/campaigns', { preserveScroll: true });
        }
    };

    return (
        <AdminLayout>
            {Object.keys(errors).length > 0 && <div role="alert" className="mb-4 rounded-control border border-danger p-4 text-sm text-danger">
                <p className="font-semibold">No se pudo guardar la campaña. Revisa estos campos:</p>
                <ul className="mt-2 list-inside list-disc">{Object.entries(errors).map(([field, message]) => <li key={field}>{message}</li>)}</ul>
            </div>}
            <Head title={campaign ? `Editar ${campaign.name}` : 'Nueva campaña'} />

            <PageHeader
                eyebrow="Campañas"
                title={campaign ? `Editar ${campaign.name}` : 'Nueva campaña'}
                description="Configura la campaña en pasos: información, creatividades, segmentación, programación y publicación."
                actions={
                    <div className="flex max-w-full flex-wrap gap-2">
                        <Button variant="secondary" size="sm" onClick={() => submit(false)} disabled={form.processing}>
                            <Save className="size-4" />
                            Guardar borrador
                        </Button>
                        <Button variant="primary" size="sm" onClick={() => submit(true)} disabled={form.processing || !canAdvance()}>
                            <Rocket className="size-4" />
                            {campaign ? 'Guardar y publicar' : 'Publicar campaña'}
                        </Button>
                    </div>
                }
            />

            <div className="mt-6 flex flex-wrap items-center gap-2">
                {steps.map((item, index) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => setStep(item.id)}
                        className={cn(
                            'flex items-center gap-2 rounded-control border px-3 py-1.5 text-xs transition-colors',
                            step === item.id
                                ? 'border-accent/40 bg-accent/10 text-accent'
                                : step > item.id
                                  ? 'border-line bg-surface text-muted'
                                  : 'border-line bg-surface text-faint',
                        )}
                    >
                        <span className="metric">{index + 1}</span>
                        {item.label}
                    </button>
                ))}
            </div>

            <Card className="mt-4">
                <CardContent className="pt-5">
                    {step === 0 ? (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField label="Nombre de la campaña" error={errors.name} className="sm:col-span-2">
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Refresca tu momento" />
                            </FormField>
                            <FormField label="Anunciante" error={errors.advertiser_id}>
                                <Select value={data.advertiser_id || undefined} onValueChange={(value) => setData('advertiser_id', value)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona anunciante" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.advertisers.map((advertiser) => (
                                            <SelectItem key={advertiser.id} value={String(advertiser.id)}>
                                                {advertiser.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField label="Prioridad (1–10)" error={errors.priority}>
                                <Input type="number" min={1} max={10} value={data.priority} onChange={(e) => setData('priority', Number(e.target.value))} />
                            </FormField>
                            <FormField label="Descripción" error={errors.description} className="sm:col-span-2">
                                <Textarea value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            </FormField>
                        </div>
                    ) : null}

                    {step === 1 ? (
                        <div className="space-y-5">
                            <div>
                                <p className="mb-2 text-xs text-muted">Seleccionadas ({data.creatives.length})</p>
                                {data.creatives.length === 0 ? (
                                    <p className="rounded-control border border-dashed border-line px-4 py-6 text-center text-xs text-faint">
                                        Aún no has agregado creatividades.
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {data.creatives.map((creative, index) => {
                                            const media = creativeOptions.find((item) => item.id === creative.media_asset_id);
                                            return (
                                                <div key={String(creative.media_asset_id)} className="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-start gap-3 rounded-control border border-line bg-surface p-2 sm:grid-cols-[96px_minmax(0,1fr)_auto]">
                                                    <MediaThumbnail media={media} className="col-span-2 w-24 sm:col-span-1" />
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate text-xs text-fg">{media?.filename ?? '—'}</p>
                                                        {(errors as Record<string, string>)[`creatives.${index}.duration`] && <p className="text-xs text-danger">{(errors as Record<string, string>)[`creatives.${index}.duration`]}</p>}
                                                        {media?.live ? <p className="mt-1 text-xs text-muted">Se reproduce durante el horario del evento.</p> : <div className="mt-1 flex flex-wrap items-center gap-2">
                                                            <label className="flex items-center gap-1 text-[11px] text-faint">
                                                                Duración
                                                                <Input
                                                                    type="number"
                                                                    min={3}
                                                                    max={86400}
                                                                    value={creative.duration}
                                                                    onChange={(e) => updateCreative(creative.media_asset_id, { duration: Number(e.target.value) })}
                                                                    className="h-7 w-24 px-2 text-xs"
                                                                />
                                                                s
                                                            </label>
                                                            <label className="flex items-center gap-1 text-[11px] text-faint">
                                                                Peso
                                                                <Input
                                                                    type="number"
                                                                    min={1}
                                                                    max={100}
                                                                    value={creative.weight}
                                                                    onChange={(e) => updateCreative(creative.media_asset_id, { weight: Number(e.target.value) })}
                                                                    className="h-7 w-16 px-2 text-xs"
                                                                />
                                                            </label>
                                                        </div>}
                                                    </div>
                                                    <Button variant="ghost" size="icon-sm" aria-label="Quitar creatividad" onClick={() => removeCreative(creative.media_asset_id)}>
                                                        <Trash2 className="size-3.5 text-danger" />
                                                    </Button>
                                                    {media?.live && creative.configuration ? <LiveCampaignFields media={media} value={creative.configuration} assets={creativeOptions} onChange={configuration => updateCreative(creative.media_asset_id, {configuration})} /> : null}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>

                            <div>
                                <p className="mb-2 text-xs text-muted">Biblioteca de creatividades</p>
                                {can('creatives.manage') ? <LiveStreamDialog onCreated={media => {setAddedSources(list => [...list, media]); addCreative(media);}} /> : null}
                                {creativeOptions.length === 0 ? (
                                    <p className="text-xs text-faint">Sube creatividades desde el módulo de creatividades.</p>
                                ) : (
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                                        {creativeOptions.map((media) => {
                                            const selected = data.creatives.some((creative) => creative.media_asset_id === media.id);
                                            return (
                                                <button
                                                    key={media.id}
                                                    type="button"
                                                    onClick={() => (selected ? removeCreative(media.id) : addCreative(media))}
                                                    className={cn(
                                                        'relative space-y-1.5 rounded-control border p-1.5 text-left transition-colors',
                                                        selected ? 'border-accent/50 bg-accent/10' : 'border-line hover:border-line-strong',
                                                    )}
                                                >
                                                    <MediaThumbnail media={media} />
                                                    <p className="truncate text-[11px] text-fg">{media.filename}</p>
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
                            </div>
                        </div>
                    ) : null}

                    {step === 2 ? (
                        <TargetBuilder
                            targets={data.targets}
                            options={options}
                            onAdd={addTarget}
                            onRemove={(index) => setData('targets', data.targets.filter((_, i) => i !== index))}
                        />
                    ) : null}

                    {step === 3 ? (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField label="Fecha de inicio" error={errors.starts_at}>
                                <Input type="date" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                            </FormField>
                            <FormField label="Fecha de fin" error={errors.ends_at}>
                                <Input type="date" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                            </FormField>
                            <FormField label="Hora de inicio diaria" error={errors.daily_start_time}>
                                <Input type="time" value={data.daily_start_time} onChange={(e) => setData('daily_start_time', e.target.value)} />
                            </FormField>
                            <FormField label="Hora de fin diaria" error={errors.daily_end_time}>
                                <Input type="time" value={data.daily_end_time} onChange={(e) => setData('daily_end_time', e.target.value)} />
                            </FormField>
                            <FormField label="Días de la semana" className="sm:col-span-2" hint="Sin días seleccionados, la campaña se reproduce todos los días.">
                                <div className="flex flex-wrap gap-1.5">
                                    {weekDays.map((day) => {
                                        const active = data.days_of_week.includes(day.value);
                                        return (
                                            <button
                                                key={day.value}
                                                type="button"
                                                onClick={() =>
                                                    setData(
                                                        'days_of_week',
                                                        active
                                                            ? data.days_of_week.filter((value) => value !== day.value)
                                                            : [...data.days_of_week, day.value],
                                                    )
                                                }
                                                className={cn(
                                                    'size-9 rounded-control border text-xs font-medium transition-colors',
                                                    active ? 'border-accent/40 bg-accent/15 text-accent' : 'border-line bg-surface text-muted',
                                                )}
                                            >
                                                {day.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            </FormField>
                        </div>
                    ) : null}

                    {step === 4 ? (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField label="Meta de reproducciones" error={errors.playback_goal} hint="Opcional: permite medir el progreso de la campaña.">
                                <Input
                                    type="number"
                                    min={0}
                                    value={data.playback_goal ?? ''}
                                    onChange={(e) => setData('playback_goal', e.target.value ? Number(e.target.value) : null)}
                                />
                            </FormField>
                            <FormField label="Meta de impresiones" error={errors.impressions_goal} hint="Opcional.">
                                <Input
                                    type="number"
                                    min={1}
                                    value={data.impressions_goal ?? ''}
                                    onChange={(e) => setData('impressions_goal', e.target.value ? Number(e.target.value) : null)}
                                />
                            </FormField>
                            <FormField label="Presupuesto (COP)" error={errors.budget}>
                                <Input
                                    type="number"
                                    min={0}
                                    value={data.budget ?? ''}
                                    onChange={(e) => setData('budget', e.target.value)}
                                />
                            </FormField>
                        </div>
                    ) : null}

                    {step === 5 ? (
                        <div className="space-y-5">
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                {[
                                    { label: 'Pantallas', value: summary?.screens ?? 0, icon: Target },
                                    { label: 'Negocios', value: summary?.businesses ?? 0, icon: Target },
                                    { label: 'Ubicaciones', value: summary?.locations ?? 0, icon: Target },
                                    { label: 'Ciudades', value: summary?.cities ?? 0, icon: CalendarDays },
                                ].map((item) => (
                                    <div key={item.label} className="rounded-control border border-line bg-surface px-3 py-3">
                                        <p className="text-[11px] text-faint">{item.label}</p>
                                        <p className="metric text-xl text-fg">{formatNumber(item.value)}</p>
                                    </div>
                                ))}
                            </div>

                            <div className="rounded-control border border-line bg-surface p-4 text-sm">
                                <p className="font-medium text-fg">{data.name}</p>
                                <p className="mt-1 text-xs text-muted">{data.description || 'Sin descripción'}</p>
                                <div className="mt-3 flex flex-wrap gap-1.5">
                                    <Badge tone="info">{data.creatives.length} creatividades</Badge>
                                    <Badge tone="neutral">{data.targets.length} reglas de segmentación</Badge>
                                    <Badge tone="accent">Prioridad {data.priority}</Badge>
                                    <Badge tone="neutral">
                                        {data.starts_at} → {data.ends_at}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    ) : null}

                    <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                        <Button variant="ghost" size="sm" onClick={() => setStep((value) => Math.max(0, value - 1))} disabled={step === 0}>
                            <ArrowLeft className="size-4" />
                            Atrás
                        </Button>
                        <span className="text-xs text-faint">
                            Paso {step + 1} de {steps.length}
                        </span>
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => setStep((value) => Math.min(steps.length - 1, value + 1))}
                            disabled={step >= steps.length - 1 || !canAdvance()}
                        >
                            Siguiente
                            <ArrowRight className="size-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}

function TargetBuilder({
    targets,
    options,
    onAdd,
    onRemove,
}: {
    targets: TargetInput[];
    options: Options;
    onAdd: (target: TargetInput) => void;
    onRemove: (index: number) => void;
}) {
    const [draft, setDraft] = useState<TargetInput>({ target_type: 'city', target_id: '', target_value: '', is_exclusion: false });

    const optionsFor = (type: string) => {
        switch (type) {
            case 'city':
                return options.cities.map((city) => ({ value: String(city.id), label: [city.name, city.state, city.country].filter(Boolean).join(' · ') }));
            case 'business_category':
                return options.categories.map((category) => ({ value: category.value, label: category.label }));
            case 'business':
                return options.businesses.map((business) => ({ value: String(business.id), label: business.name }));
            case 'location':
                return options.locations.map((location) => ({ value: String(location.id), label: `${location.name} · ${location.city}` }));
            case 'device':
                return options.devices.map((device) => ({ value: String(device.id), label: `${device.name}${device.business ? ` · ${device.business}` : ''}` }));
            default:
                return [];
        }
    };

    const isEntity = ['city', 'business', 'location', 'device'].includes(draft.target_type);
    const draftOptions = optionsFor(draft.target_type);

    const add = () => {
        if (isEntity && !draft.target_id) return;
        if (!isEntity && !draft.target_value) return;
        onAdd(draft);
        setDraft({ target_type: draft.target_type, target_id: '', target_value: '', is_exclusion: false });
    };

    return (
        <div className="space-y-5">
            <div className="rounded-control border border-line bg-surface p-4">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <FormField label="Tipo de objetivo">
                        <Select
                            value={draft.target_type}
                            onValueChange={(value) => setDraft({ ...draft, target_type: value, target_id: '', target_value: '' })}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {targetTypes.map((type) => (
                                    <SelectItem key={type.value} value={type.value}>
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField label="Valor" className="sm:col-span-2">
                        {isEntity || draftOptions.length > 0 ? (
                            <Select
                                value={isEntity ? String(draft.target_id) : draft.target_value}
                                onValueChange={(value) =>
                                    isEntity
                                        ? setDraft({ ...draft, target_id: Number(value) })
                                        : setDraft({ ...draft, target_value: value })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecciona un valor" />
                                </SelectTrigger>
                                <SelectContent>
                                    {draftOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : (
                            <Input
                                value={draft.target_value}
                                onChange={(e) => setDraft({ ...draft, target_value: e.target.value })}
                                placeholder="Escribe el valor"
                            />
                        )}
                    </FormField>

                    <div className="flex min-w-0 flex-wrap items-end gap-2">
                        <label className="flex h-10 flex-1 items-center gap-2 rounded-control border border-line bg-inset px-3 text-xs text-muted">
                            <input
                                type="checkbox"
                                checked={draft.is_exclusion}
                                onChange={(e) => setDraft({ ...draft, is_exclusion: e.target.checked })}
                                className="size-3.5 accent-danger"
                            />
                            Excluir
                        </label>
                        <Button variant="primary" onClick={add} disabled={isEntity && draftOptions.length === 0}>
                            <Target className="size-4" />
                            Añadir
                        </Button>
                    </div>
                </div>
            </div>

            <div>
                <p className="mb-2 text-xs text-muted">Reglas de segmentación ({targets.length})</p>
                {targets.length === 0 ? (
                    <p className="rounded-control border border-dashed border-line px-4 py-6 text-center text-xs text-faint">
                        Añade al menos una regla. Las reglas de inclusión se combinan con «o»; las exclusiones restan.
                    </p>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        {targets.map((target, index) => (
                            <span
                                key={`${target.target_type}-${target.target_id}-${target.target_value}-${index}`}
                                className={cn(
                                    'inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs',
                                    target.is_exclusion ? 'border-danger/30 bg-danger/10 text-danger' : 'border-info/30 bg-info/10 text-info',
                                )}
                            >
                                {target.is_exclusion ? 'Excluye ' : ''}
                                {targetTypes.find((type) => type.value === target.target_type)?.label}
                                {target.target_id ? `: ${optionsFor(target.target_type).find((option) => option.value === String(target.target_id))?.label ?? `#${target.target_id}`}` : ''}
                                {target.target_value ? `: ${target.target_value}` : ''}
                                <button type="button" onClick={() => onRemove(index)} aria-label="Quitar">
                                    <X className="size-3" />
                                </button>
                            </span>
                        ))}
                    </div>
                )}
            </div>

            <p className="flex items-center gap-2 text-[11px] text-faint">
                <ImageIcon className="size-3.5" />
                La red cuenta con {formatNumber(options.counts.screens)} pantallas, {options.counts.businesses} negocios y{' '}
                {options.counts.cities} ciudades disponibles.
            </p>
        </div>
    );
}
