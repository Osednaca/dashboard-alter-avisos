import { useState } from 'react';
import { FormField } from './FormField';
import { LivePreview } from './LiveStreamDialog';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import type { LiveConfiguration } from '@/Utils/campaignForm';
import type { MediaEntity } from '@/Types';

export function LiveCampaignFields({media, value, onChange, assets}: {media: MediaEntity; value: LiveConfiguration; onChange: (value: LiveConfiguration) => void; assets: MediaEntity[]}) {
    const [preview, setPreview] = useState(false);
    const patch = (p: Partial<LiveConfiguration>) => onChange({...value, ...p});
    const local = (iso: string) => { if(!iso) return ''; const d = new Date(iso); return new Date(d.getTime() - d.getTimezoneOffset()*60000).toISOString().slice(0,16); };
    return <div className="col-span-full grid min-w-0 grid-cols-1 gap-3 border-t border-line pt-3 sm:grid-cols-2">
        <FormField label="Inicio del evento (hora local)"><Input type="datetime-local" value={local(value.starts_at)} onChange={e => patch({starts_at: e.target.value ? new Date(e.target.value).toISOString() : ''})} /></FormField>
        <FormField label="Fin del evento (hora local)"><Input type="datetime-local" value={local(value.ends_at)} onChange={e => patch({ends_at: e.target.value ? new Date(e.target.value).toISOString() : ''})} /></FormField>
        <FormField label="Reproducción"><p className="rounded-control border border-line bg-surface p-2 text-sm">Pantalla completa</p></FormField>
        <FormField label="Audio"><select className="w-full rounded-control border border-line bg-surface p-2" value={value.audio ? 'live' : 'muted'} onChange={e => patch({audio:e.target.value==='live'})}>
            <option value="muted">Silenciado</option><option value="live">Audio del directo</option></select></FormField>
        <FormField label="Contenido de respaldo" className="sm:col-span-2"><select className="w-full rounded-control border border-line bg-surface p-2" value={value.fallback_media_id ?? ''} onChange={e => patch({fallback_media_id:e.target.value ? Number(e.target.value) : null})}>
            <option value="">Lista publicitaria normal</option>{assets.filter(a => a.type.value !== 'live_stream').map(a => <option key={a.id} value={a.id}>{a.filename}</option>)}</select></FormField>
        <p className="text-xs text-muted sm:col-span-2">El directo ocupa el aviso completo durante el evento, dentro del calendario de la campaña. YouTube requiere al menos 200×200 px; Twitch, 400×300 px.</p>
        <label className="flex items-start gap-2 text-xs text-muted sm:col-span-2"><input type="checkbox" checked={value.size_acknowledged} onChange={e => patch({size_acknowledged:e.target.checked})} />Revisé el tamaño del aviso y la prueba del proveedor.</label>
        <Button variant="secondary" onClick={() => setPreview(!preview)}>{preview ? 'Cerrar prueba' : 'Probar directo'}</Button>
        {preview && media.live ? <div className="sm:col-span-2"><LivePreview source={media.live} /></div> : null}
    </div>;
}
