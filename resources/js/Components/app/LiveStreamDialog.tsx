import { useEffect, useRef, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { FormField } from '@/Components/app/FormField';
import type { MediaEntity } from '@/Types';

export interface LiveSource { provider: 'hls' | 'youtube' | 'twitch' | 'kick'; original_url: string; source_id: string; embed_url?: string }
export async function liveRequest(path: string, body: object, signal?: AbortSignal) {
    const response = await fetch(path, {method: 'POST', signal, headers: {'Content-Type': 'application/json', Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''}, body: JSON.stringify(body)});
    const data = await response.json();
    if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat().join(' ') || data.message || 'No se pudo completar la solicitud');
    return data;
}

export function LivePreview({source, embedUrl, compact = false, fit = 'contain'}: {source: LiveSource; embedUrl?: string | null; compact?: boolean; fit?: 'contain' | 'cover'}) {
    const video = useRef<HTMLVideoElement>(null);
    const frame = useRef<HTMLIFrameElement>(null);
    const [status, setStatus] = useState('Conectando…');
    useEffect(() => {
        setStatus('Conectando…');
        const statuses: Record<string, string> = {live: 'Reproduciendo', buffering: 'Cargando…', offline: 'Sin conexión / canal desconectado', failed: 'No disponible', ended: 'Finalizado', unverified: 'Reproductor cargado. Kick no permite confirmar automáticamente si está en vivo.'};
        const listener = (e: MessageEvent) => {
            if (e.origin === location.origin && e.source === frame.current?.contentWindow && e.data?.type === 'signage-live')
                setStatus(statuses[e.data.state] ?? 'Conectando…');
        };
        window.addEventListener('message', listener);
        return () => window.removeEventListener('message', listener);
    }, [source.original_url]);
    useEffect(() => {
        if (source.provider !== 'hls' || !video.current) return;
        let disposed = false;
        let destroy: (() => void) | undefined;
        const element = video.current;
        if (element.canPlayType('application/vnd.apple.mpegurl')) { element.src = source.original_url; }
        else void import('hls.js').then(({default: Hls}) => {
            if (disposed) return;
            if (!Hls.isSupported()) { setStatus('Este navegador no admite HLS'); return; }
            const hls = new Hls(); destroy = () => hls.destroy();
            hls.on(Hls.Events.ERROR, (_, data) => { if(data.fatal) setStatus('HLS no disponible. Revisa conexión, CORS y URL.'); });
            hls.loadSource(source.original_url); hls.attachMedia(element);
        }).catch(() => setStatus('No se pudo cargar el reproductor HLS'));
        return () => { disposed = true; destroy?.(); element.pause(); element.removeAttribute('src'); element.load(); };
    }, [source.provider, source.original_url]);
    return <div className={compact ? 'relative size-full' : 'space-y-2'}>
        {source.provider === 'hls' ? <video ref={video} controls={!compact} autoPlay muted playsInline className={compact ? (fit === 'cover' ? 'size-full object-cover bg-black' : 'size-full object-contain bg-black') : 'aspect-video w-full rounded-control bg-black'}
            onPlaying={() => setStatus('Reproduciendo')} onWaiting={() => setStatus('Cargando…')} onError={() => setStatus('HLS no disponible')} />
            : <iframe ref={frame} src={embedUrl ?? source.embed_url} title="Prueba del directo" allow="autoplay; encrypted-media" referrerPolicy="strict-origin-when-cross-origin" className={compact ? 'size-full border-0' : 'h-[320px] w-full rounded-control border-0'} />}
        <p role="status" className={compact ? 'absolute right-1 top-1 max-w-full rounded bg-black/80 px-1 text-[10px] text-white' : 'text-xs text-muted'}>{compact ? 'Reproductor web: ' : ''}{status}</p>
        {!compact ? <p className="text-xs text-muted">La prueba no garantiza disponibilidad futura. El autoplay depende del proveedor y del dispositivo.</p> : null}
    </div>;
}

export function LiveStreamDialog({onCreated}: {onCreated: (media: MediaEntity) => void}) {
    const [open, setOpen] = useState(false);
    const [url, setUrl] = useState('');
    const [name, setName] = useState('');
    const [detected, setDetected] = useState<{source: LiveSource; preview_url: string | null} | null>(null);
    const [error, setError] = useState('');
    const [preview, setPreview] = useState(false);
    const [saving, setSaving] = useState(false);
    useEffect(() => {
        setDetected(null); setPreview(false); setError('');
        if (!open || !url.trim()) return;
        const controller = new AbortController();
        const timer = setTimeout(() => { void liveRequest('/admin/live-streams/detect', {url}, controller.signal).then(setDetected)
            .catch(e => { if(!controller.signal.aborted) setError(e.message); }); }, 500);
        return () => { clearTimeout(timer); controller.abort(); };
    }, [url, open]);
    const save = async () => {
        setSaving(true); setError('');
        try { const result = await liveRequest('/admin/live-streams', {url, name}); onCreated(result.media); setOpen(false); setUrl(''); setName(''); }
        catch(e) { setError(e instanceof Error ? e.message : 'Error al guardar'); }
        finally { setSaving(false); }
    };
    return <><Button variant="secondary" size="sm" onClick={() => setOpen(true)}>Agregar directo</Button>
        <Dialog open={open} onOpenChange={setOpen}><DialogContent className="max-h-[90vh] overflow-y-auto"><DialogHeader><DialogTitle>Agregar transmisión en vivo</DialogTitle></DialogHeader>
            <FormField label="URL del directo"><Input value={url} onChange={e => setUrl(e.target.value)} placeholder="https://www.youtube.com/watch?v=…" /></FormField>
            <FormField label="Nombre (opcional)"><Input value={name} onChange={e => setName(e.target.value)} maxLength={180} /></FormField>
            {detected ? <p className="break-all text-sm text-fg">{detected.source.provider.toUpperCase()} detectado · {detected.source.provider === 'hls' ? 'HLS aprobado' : detected.source.source_id}</p> : null}
            {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
            {preview && detected ? <LivePreview source={detected.source} embedUrl={detected.preview_url} /> : null}
            <div className="flex flex-wrap gap-2"><Button variant="secondary" disabled={!detected} onClick={() => setPreview(true)}>Probar directo</Button>
                <Button disabled={!detected || saving} onClick={() => void save()}>Guardar fuente</Button></div>
        </DialogContent></Dialog></>;
}
