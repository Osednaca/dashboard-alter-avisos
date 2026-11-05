import axios from 'axios';
import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Library, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { MediaPicker } from '@/Components/app/MediaPicker';
import { MediaThumbnail } from '@/Components/app/MediaThumbnail';
import { UploadDropzone } from '@/Components/app/UploadDropzone';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import type { ContentScheduleEntity, MediaEntity, Option } from '@/Types';
import { cn } from '@/Utils/cn';
import { appendScheduleItems, applyScheduleMediaStatus, hasUnreadyScheduleItems, hydrateSchedule, moveScheduleItem, removeScheduleItem, schedulePayload, type ScheduleDraftItem, type LegacyScheduleContent } from '@/Utils/schedule-state';

export type { LegacyScheduleContent } from '@/Utils/schedule-state';
const days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

export function ScheduleComposer({ schedule, imported, selectedMedia = [], availableMedia, legacyPlaylists, transitions, locations, canUpload, onClose }: {
    schedule: ContentScheduleEntity | null;
    imported?: LegacyScheduleContent;
    selectedMedia?: MediaEntity[];
    availableMedia: MediaEntity[];
    legacyPlaylists: LegacyScheduleContent[];
    transitions: Option[];
    locations: Array<{ id: number; name: string; city: string }>;
    canUpload: boolean;
    onClose: () => void;
}) {
    const initialItems = schedule?.items ?? imported?.items ?? [];
    const nextKey = useRef(initialItems.length || selectedMedia.length);
    const [assets, setAssets] = useState<Record<number, MediaEntity>>(() => Object.fromEntries(
        [...availableMedia, ...selectedMedia, ...initialItems.flatMap((item) => item.media ? [item.media] : [])].map((asset) => [asset.id, asset]),
    ));
    const form = useForm(hydrateSchedule(schedule, imported, selectedMedia));
    const [pickerOpen, setPickerOpen] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [statusError, setStatusError] = useState('');
    const [pollRetry, setPollRetry] = useState(0);
    const [localError, setLocalError] = useState('');
    const nameRef = useRef<HTMLInputElement>(null);
    const errors = form.errors as Record<string, string>;
    const busy = uploading || form.processing;
    const pendingIds = [...new Set(form.data.items.map((item) => item.media_asset_id))]
        .filter((id) => assets[id] && !['ready', 'failed'].includes(assets[id].processing_status.value)).sort((a, b) => a - b).join(',');
    const unready = hasUnreadyScheduleItems(form.data.items, assets);

    useEffect(() => { nameRef.current?.focus(); }, []);
    useEffect(() => {
        if (!pendingIds) { setStatusError(''); return; }
        const controller = new AbortController();
        let timer: ReturnType<typeof setTimeout>;
        const refresh = async () => {
            try {
                const ids = pendingIds.split(',').map(Number);
                const media: MediaEntity[] = [];
                for (let offset = 0; offset < ids.length; offset += 100) {
                    const query = new URLSearchParams();
                    ids.slice(offset, offset + 100).forEach((id) => query.append('ids[]', String(id)));
                    const response = await axios.get<{ media: MediaEntity[] }>(`/business/schedule/media-status?${query}`, { signal: controller.signal });
                    media.push(...response.data.media);
                }
                if (controller.signal.aborted) return;
                setAssets((current) => applyScheduleMediaStatus(current, ids, media));
                setStatusError('');
            } catch {
                if (!controller.signal.aborted) setStatusError('No se pudo comprobar el procesamiento. Tu formulario sigue intacto.');
            } finally {
                if (!controller.signal.aborted) timer = setTimeout(() => void refresh(), 6000);
            }
        };
        void refresh();
        return () => { controller.abort(); clearTimeout(timer); };
    }, [pendingIds, pollRetry]);

    const addAsset = (asset: MediaEntity) => {
        setAssets((current) => ({ ...current, [asset.id]: asset }));
        const item: ScheduleDraftItem = {
            key: nextKey.current++, media_asset_id: asset.id,
            duration_seconds: Math.min(600, Math.max(3, Math.round(asset.duration ?? 10))),
            transition: transitions[0]?.value ?? 'none',
        };
        form.setData((current) => appendScheduleItems(current, [item]));
    };
    const importContent = (id: string) => {
        const playlist = legacyPlaylists.find((value) => String(value.id) === id);
        if (!playlist) return;
        setAssets((current) => ({ ...current, ...Object.fromEntries(playlist.items.flatMap((item) => item.media ? [[item.media.id, item.media]] : [])) }));
        const items = playlist.items.map((item) => ({ key: nextKey.current++, media_asset_id: item.media?.id ?? 0, duration_seconds: item.duration, transition: item.transition }));
        form.setData((current) => appendScheduleItems(current, items));
    };
    const updateItem = (key: number, patch: Partial<ScheduleDraftItem>) => form.setData((current) => ({
        ...current, items: current.items.map((item) => item.key === key ? { ...item, ...patch } : item),
    }));
    const move = (index: number, direction: -1 | 1) => form.setData((current) => moveScheduleItem(current, index, direction));
    const submit = (event: FormEvent) => {
        event.preventDefault();
        setLocalError('');
        if (busy || unready) return;
        if (form.data.items.length === 0) { setLocalError('Agrega al menos una imagen o un video.'); return; }
        if (form.data.items.length > 100) { setLocalError('Cada programación admite hasta 100 elementos. Quita los adicionales antes de guardar.'); return; }
        form.transform(schedulePayload);
        const options = { preserveScroll: true, onSuccess: onClose };
        if (schedule) form.put(`/business/schedule/${schedule.id}`, options);
        else form.post('/business/schedule', options);
    };
    const fieldError = (field: string) => errors[field] ? <p className="text-xs text-danger">{errors[field]}</p> : null;

    return (
        <form onSubmit={submit} className="mt-6 min-w-0 space-y-8 rounded-card border border-line bg-card p-4 sm:p-6" aria-label={schedule ? 'Editar programación' : 'Nueva programación'}>
            <div className="space-y-2">
                <h2 className="text-lg font-semibold text-fg">{schedule ? 'Editar programación' : 'Nueva programación'}</h2>
                <p className="text-sm text-muted">El contenido y los horarios se guardan juntos. Los cambios afectan solo a esta programación.</p>
                <label htmlFor="schedule-name" className="block pt-2 text-sm font-medium text-fg">Nombre</label>
                <Input ref={nameRef} id="schedule-name" required maxLength={120} placeholder="Ej: Menú del almuerzo" value={form.data.name} disabled={busy} onChange={(event) => form.setData('name', event.target.value)} />
                {fieldError('name')}
            </div>

            <section aria-labelledby="schedule-content-title" className="min-w-0 space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 id="schedule-content-title" className="font-semibold text-fg">Qué se muestra</h3>
                        <p className="mt-1 text-xs text-muted">{form.data.items.length} de 100 elementos · se reproducen de arriba hacia abajo.</p>
                    </div>
                    <Button type="button" variant="secondary" disabled={busy} onClick={() => setPickerOpen(true)}><Library />Elegir de la biblioteca</Button>
                </div>
                {canUpload ? <UploadDropzone action="/business/library" disabled={form.processing} reloadOnComplete={false} onBusyChange={setUploading} onAssetUploaded={addAsset} /> : null}
                <p className="text-xs text-muted">Subir no publica. Los archivos quedan en la biblioteca aunque canceles esta programación.</p>
                {legacyPlaylists.length > 0 ? (
                    <details className="text-sm text-muted">
                        <summary className="cursor-pointer py-2">Copiar contenido guardado anteriormente</summary>
                        <div className="mt-2 space-y-2">
                            <p className="text-xs">Se agregará una copia independiente al final, sin cambiar el contenido original.</p>
                            <Select value="" onValueChange={importContent} disabled={busy}>
                                <SelectTrigger aria-label="Copiar contenido anterior"><SelectValue placeholder="Elegir contenido anterior" /></SelectTrigger>
                                <SelectContent>{legacyPlaylists.map((playlist) => <SelectItem key={playlist.id} value={String(playlist.id)}>{playlist.name}</SelectItem>)}</SelectContent>
                            </Select>
                        </div>
                    </details>
                ) : null}
                {form.data.items.length === 0 ? <p className="rounded-control border border-dashed border-line p-6 text-center text-sm text-muted">Sube archivos o elige contenido de la biblioteca para empezar.</p> : (
                    <ol className="space-y-3">
                        {form.data.items.map((item, index) => {
                            const asset = assets[item.media_asset_id];
                            const supported = asset && ['image', 'video'].includes(asset.type.value);
                            const ready = supported && asset.processing_status.value === 'ready';
                            const failed = !supported || asset.processing_status.value === 'failed';
                            return (
                                <li key={item.key} className="min-w-0 border-b border-line pb-4 last:border-0">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <span className="metric w-6 shrink-0 text-sm text-muted">{index + 1}</span>
                                        <MediaThumbnail media={asset} className="w-20 shrink-0" />
                                        <div className="min-w-0 flex-1">
                                            <p className="break-words text-sm font-medium text-fg">{asset?.filename ?? 'Contenido no disponible'}</p>
                                            <p className={cn('mt-1 text-xs', failed ? 'text-danger' : 'text-muted')}>
                                                {ready ? asset.type.label : failed ? 'No disponible. Quita este elemento y vuelve a subirlo.' : 'Procesando. Espera antes de guardar.'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-end gap-3">
                                        <div className="space-y-1">
                                            <label htmlFor={`duration-${item.key}`} className="block text-xs text-muted">Duración (seg)</label>
                                            <Input id={`duration-${item.key}`} type="number" min={3} max={600} step={1} required disabled={busy} value={item.duration_seconds || ''} className="w-24" onChange={(event) => updateItem(item.key, { duration_seconds: Number(event.target.value) })} />
                                        </div>
                                        <div className="min-w-0 flex-1 basis-40 space-y-1">
                                            <label htmlFor={`transition-${item.key}`} className="block text-xs text-muted">Transición</label>
                                            <Select value={item.transition} onValueChange={(transition) => updateItem(item.key, { transition })} disabled={busy}>
                                                <SelectTrigger id={`transition-${item.key}`}><SelectValue /></SelectTrigger>
                                                <SelectContent>{transitions.map((transition) => <SelectItem key={transition.value} value={transition.value}>{transition.label}</SelectItem>)}</SelectContent>
                                            </Select>
                                        </div>
                                        <div className="flex shrink-0 gap-1">
                                            <Button type="button" variant="ghost" size="icon" disabled={busy || index === 0} onClick={() => move(index, -1)} aria-label={`Subir elemento ${index + 1}`}><ArrowUp /></Button>
                                            <Button type="button" variant="ghost" size="icon" disabled={busy || index === form.data.items.length - 1} onClick={() => move(index, 1)} aria-label={`Bajar elemento ${index + 1}`}><ArrowDown /></Button>
                                            <Button type="button" variant="ghost" size="icon" disabled={busy} onClick={() => form.setData((current) => removeScheduleItem(current, item.key))} aria-label={`Quitar elemento ${index + 1}`}><Trash2 className="text-danger" /></Button>
                                        </div>
                                    </div>
                                    {Object.entries(errors).filter(([key]) => key.startsWith(`items.${index}.`)).map(([key, message]) => <p key={key} className="mt-2 text-xs text-danger">{message}</p>)}
                                </li>
                            );
                        })}
                    </ol>
                )}
                {fieldError('items')}
                {statusError ? <div role="status" className="flex flex-wrap items-center gap-2 text-xs text-warning">{statusError}<Button type="button" size="sm" variant="ghost" onClick={() => setPollRetry((value) => value + 1)}>Reintentar comprobación</Button></div> : null}
            </section>

            <section aria-labelledby="schedule-timing-title" className="space-y-4 border-t border-line pt-6">
                <h3 id="schedule-timing-title" className="font-semibold text-fg">Cuándo y dónde</h3>
                <div className="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="min-w-0 space-y-1.5 sm:col-span-2">
                        <label htmlFor="schedule-location" className="text-sm text-muted">Ubicación</label>
                        <Select value={form.data.location_id || 'all'} disabled={busy} onValueChange={(value) => form.setData('location_id', value === 'all' ? '' : value)}>
                            <SelectTrigger id="schedule-location"><SelectValue /></SelectTrigger>
                            <SelectContent><SelectItem value="all">Todas las ubicaciones</SelectItem>{locations.map((location) => <SelectItem key={location.id} value={String(location.id)}>{location.name}</SelectItem>)}</SelectContent>
                        </Select>
                        {fieldError('location_id')}
                    </div>
                    <div className="space-y-1.5">
                        <label htmlFor="schedule-start" className="text-sm text-muted">Hora de inicio</label>
                        <Input id="schedule-start" type="time" disabled={busy} value={form.data.daily_start_time} onChange={(event) => form.setData('daily_start_time', event.target.value)} />
                        {fieldError('daily_start_time')}
                    </div>
                    <div className="space-y-1.5">
                        <label htmlFor="schedule-end" className="text-sm text-muted">Hora de fin</label>
                        <Input id="schedule-end" type="time" disabled={busy} value={form.data.daily_end_time} onChange={(event) => form.setData('daily_end_time', event.target.value)} />
                        {fieldError('daily_end_time')}
                    </div>
                </div>
                <p className="text-xs text-muted">Deja ambas horas vacías para reproducir todo el día.</p>
                <fieldset disabled={busy} className="space-y-2">
                    <legend className="text-sm text-muted">Días de reproducción</legend>
                    <div className="flex flex-wrap gap-2">{days.map((day, index) => {
                        const value = index + 1;
                        const selected = form.data.days_of_week.includes(value);
                        return <button key={day} type="button" aria-pressed={selected} aria-label={day} className={cn('min-h-11 min-w-11 rounded-control border px-3 text-sm disabled:opacity-50', selected ? 'border-accent/50 bg-accent/15 text-accent' : 'border-line text-muted')} onClick={() => form.setData('days_of_week', selected ? form.data.days_of_week.filter((entry) => entry !== value) : [...form.data.days_of_week, value])}>{day.slice(0, 3)}</button>;
                    })}</div>
                    <p className="text-xs text-muted">Sin días seleccionados: todos los días.</p>
                    {fieldError('days_of_week')}
                </fieldset>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="min-w-0 space-y-1.5">
                        <label htmlFor="schedule-status" className="text-sm text-muted">Estado</label>
                        <Select value={form.data.status} disabled={busy} onValueChange={(value) => form.setData('status', value)}>
                            <SelectTrigger id="schedule-status"><SelectValue /></SelectTrigger>
                            <SelectContent><SelectItem value="active">Activa</SelectItem><SelectItem value="inactive">Inactiva</SelectItem></SelectContent>
                        </Select>
                        {fieldError('status')}
                    </div>
                    <div className="space-y-1.5">
                        <label htmlFor="schedule-priority" className="text-sm text-muted">Prioridad</label>
                        <Input id="schedule-priority" type="number" min={0} max={100} required disabled={busy} value={form.data.priority} onChange={(event) => form.setData('priority', Number(event.target.value))} />
                        <p className="text-xs text-muted">Si coinciden horarios, se elige la de mayor prioridad.</p>
                        {fieldError('priority')}
                    </div>
                </div>
            </section>
            <div className="space-y-3 border-t border-line pt-4">
                {localError ? <p role="alert" className="text-sm text-danger">{localError}</p> : null}
                {Object.keys(errors).length > 0 ? <p role="alert" className="text-sm text-danger">Revisa los campos indicados. La programación no se ha guardado.</p> : null}
                {unready ? <p role="status" className="text-sm text-warning">Hay contenido pendiente o no disponible. Espera a que termine de procesarse o quítalo para guardar.</p> : null}
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button type="button" variant="ghost" disabled={busy} onClick={onClose}>Cancelar</Button>
                    <Button type="submit" variant="primary" disabled={busy || unready}>{uploading ? 'Subiendo archivos…' : form.processing ? 'Guardando…' : 'Guardar programación'}</Button>
                </div>
            </div>
            <MediaPicker endpoint="/business/schedule/media" open={pickerOpen} onOpenChange={setPickerOpen} media={Object.values(assets).filter((asset) => asset.processing_status.value === 'ready' && ['image', 'video'].includes(asset.type.value))} selectedIds={form.data.items.map((item) => item.media_asset_id)} onSelect={(asset) => { addAsset(asset); setPickerOpen(false); }} />
        </form>
    );
}
