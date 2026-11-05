import type { MediaEntity } from '@/Types';
import { canSelectLibraryMedia } from '@/Utils/library-selection';
import { Button } from '@/Components/ui/button';

export function LibrarySelectionCheckbox({ media, selected, full, onToggle }: {
    media: MediaEntity; selected: boolean; full: boolean; onToggle: () => void;
}) {
    const ready = canSelectLibraryMedia(media);
    return (
        <label className="flex min-h-11 min-w-11 items-center justify-center rounded-control border border-line bg-card" title={!ready ? 'Disponible cuando la imagen o el video esté listo' : full && !selected ? 'Se alcanzó el máximo de elementos' : 'Seleccionar para programar'}>
            <input type="checkbox" className="size-4 accent-accent" aria-label={`Seleccionar ${media.filename}`} checked={selected} disabled={!ready || (full && !selected)} onChange={onToggle} />
        </label>
    );
}

export function LibrarySelectionBar({ count, limit, label, onClear, onCreate }: {
    count: number; limit: number; label: string; onClear: () => void; onCreate: () => void;
}) {
    return (
        <div className="mt-4 flex flex-wrap items-center gap-3 rounded-control border border-line bg-surface p-3">
            <p className="mr-auto text-xs text-muted" role="status">{count} seleccionados · Máximo {limit}. Elige imágenes y videos listos en el orden de reproducción.</p>
            {count > 0 ? <Button variant="ghost" size="sm" onClick={onClear}>Limpiar selección</Button> : null}
            <Button variant="primary" size="sm" disabled={count === 0} onClick={onCreate}>{label}</Button>
        </div>
    );
}
