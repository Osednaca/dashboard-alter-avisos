import axios from 'axios';
import { router } from '@inertiajs/react';
import { Loader2, UploadCloud } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Progress } from '@/Components/ui/progress';
import { cn } from '@/Utils/cn';
import type { MediaEntity } from '@/Types';

type Entry = { id: number; file: File; status: 'pending' | 'uploading' | 'done' | 'failed'; progress: number; error?: string };

/** One request per file keeps large batches below the server's request-size limit. */
export function UploadDropzone({ action, accept = '.jpg,.jpeg,.png,.webp,.mp4', hint = 'JPG, PNG, WebP o MP4. Puedes seleccionar varios archivos.', disabled = false, metadata = {}, onBusyChange, onUploaded, onAssetUploaded, reloadOnComplete = true }: {
    action: string;
    accept?: string;
    hint?: string;
    disabled?: boolean;
    metadata?: Record<string, string>;
    onBusyChange?: (busy: boolean) => void;
    onUploaded?: () => void;
    onAssetUploaded?: (media: MediaEntity) => void;
    reloadOnComplete?: boolean;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const running = useRef(false);
    const nextId = useRef(0);
    const [entries, setEntries] = useState<Entry[]>([]);
    const [busy, setBusy] = useState(false);
    const [dragging, setDragging] = useState(false);
    const update = (id: number, patch: Partial<Entry>) => setEntries((rows) => rows.map((row) => row.id === id ? { ...row, ...patch } : row));

    const run = async (batch: Entry[]) => {
        if (running.current || disabled || batch.length === 0) return;
        running.current = true;
        setBusy(true);
        onBusyChange?.(true);
        let failed = false;
        try {
            for (const entry of batch) {
                update(entry.id, { status: 'uploading', progress: 0, error: undefined });
                const payload = new FormData();
                payload.append('file', entry.file);
                Object.entries(metadata).forEach(([key, value]) => { if (value) payload.append(key, value); });
                try {
                    const response = await axios.post<{ media: MediaEntity }>(action, payload, {
                        headers: { Accept: 'application/json' },
                        onUploadProgress: ({ loaded, total }) => update(entry.id, { progress: total ? Math.round(loaded * 100 / total) : 0 }),
                    });
                    update(entry.id, { status: 'done', progress: 100 });
                    onAssetUploaded?.(response.data.media);
                } catch (error) {
                    failed = true;
                    const response = axios.isAxiosError(error) ? error.response : undefined;
                    const errors = response?.data?.errors as Record<string, string[]> | undefined;
                    const message = errors ? Object.values(errors).flat().join(' ') : response?.status === 413 ? 'El archivo supera el límite del servidor.' : 'No se pudo subir. Revisa la conexión y reintenta este archivo.';
                    update(entry.id, { status: 'failed', error: message });
                }
            }
        } finally {
            running.current = false;
            setBusy(false);
            onBusyChange?.(false);
            if (reloadOnComplete) {
                router.reload({ onSuccess: () => { if (!failed) onUploaded?.(); } });
            } else if (!failed) {
                onUploaded?.();
            }
        }
    };
    const select = (files: FileList | null) => {
        if (!files || running.current || disabled) return;
        const batch: Entry[] = Array.from(files).map((file) => ({ id: nextId.current++, file, status: 'pending', progress: 0 }));
        setEntries((rows) => [...rows, ...batch]);
        void run(batch);
    };
    const failures = entries.filter((entry) => entry.status === 'failed');
    return (
        <div className="min-w-0 space-y-3">
            <div onDragOver={(event) => { event.preventDefault(); if (!busy && !disabled) setDragging(true); }} onDragLeave={() => setDragging(false)}
                onDrop={(event) => { event.preventDefault(); setDragging(false); select(event.dataTransfer.files); }}
                className={cn('rounded-card border border-dashed p-5 text-center', dragging ? 'border-accent bg-surface' : 'border-line bg-surface', disabled && 'opacity-60')}>
                <input ref={inputRef} type="file" multiple accept={accept} disabled={disabled || busy} className="hidden" aria-label="Seleccionar archivos para subir"
                    onChange={(event) => { select(event.target.files); event.target.value = ''; }} />
                <div className="flex flex-col items-center gap-3">
                    {busy ? <Loader2 className="size-6 animate-spin text-accent" /> : <UploadCloud className="size-6 text-accent" />}
                    <p className="text-sm text-fg">{busy ? 'Subiendo archivos…' : 'Arrastra tus imágenes y videos aquí'}</p>
                    <Button type="button" variant="secondary" disabled={disabled || busy} onClick={() => inputRef.current?.click()}>Seleccionar archivos</Button>
                    <p className="text-xs text-muted">{hint}</p>
                </div>
            </div>
            {entries.length > 0 && <div className="space-y-2" aria-live="polite">
                <p className="text-xs text-muted">{entries.filter((entry) => entry.status === 'done').length} de {entries.length} archivos subidos</p>
                <ul className="max-h-64 space-y-3 overflow-y-auto">
                    {entries.map((entry) => <li key={entry.id} className="min-w-0 space-y-1 text-xs">
                        <p className="break-all text-fg">{entry.file.name}</p>
                        <p className={entry.status === 'failed' ? 'text-danger' : 'text-muted'}>{entry.status === 'done' ? 'Subido · procesamiento en curso' : entry.status === 'failed' ? entry.error : entry.status === 'uploading' ? `Subiendo ${entry.progress}%` : 'En espera'}</p>
                        {entry.status === 'uploading' && <Progress value={entry.progress} />}
                    </li>)}
                </ul>
                {failures.length > 0 && <Button type="button" variant="secondary" disabled={busy || disabled} onClick={() => void run(failures)}>Reintentar {failures.length} archivo(s) fallido(s)</Button>}
            </div>}
        </div>
    );
}
