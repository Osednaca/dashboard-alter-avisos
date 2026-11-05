import { Link, useForm } from '@inertiajs/react';
import { RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/Components/app/ConfirmDialog';
import { Button } from '@/Components/ui/button';
import { usePermissions } from '@/Hooks/usePermissions';
import type { QuickPlayEntity } from '@/Types';

export function QuickPlayActions({ quickPlay, portal }: { quickPlay: QuickPlayEntity; portal: 'admin' | 'business' }) {
    const { can } = usePermissions();
    const form = useForm({});
    const [confirmDelete, setConfirmDelete] = useState(false);
    const basePath = `/${portal}/quick-play`;
    const canManage = can(portal === 'business' ? 'business.playlists.manage' : 'quick_play.send');
    if (!canManage) return null;

    const options = {
        preserveScroll: true,
        onError: (errors: Record<string, string>) => toast.error(Object.values(errors)[0] ?? 'No se pudo completar la acción.'),
    };

    return (
        <div className="flex items-center gap-2" onClick={(event) => event.stopPropagation()}>
            {quickPlay.can_retry ? (
                <Button variant="secondary" size="sm" disabled={form.processing}
                    onClick={() => form.post(`${basePath}/${quickPlay.id}/retry`, options)}>
                    <RotateCcw className="size-4" />
                    {form.processing ? 'Procesando…' : 'Reintentar fallidas'}
                </Button>
            ) : null}
            {quickPlay.retry_id ? (
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`${basePath}/${quickPlay.retry_id}`}>Ver reintento</Link>
                </Button>
            ) : null}
            <Button variant="ghost" size="sm" className="text-danger hover:text-danger" disabled={form.processing}
                onClick={() => setConfirmDelete(true)} aria-label={`Eliminar Instant Play #${quickPlay.id}`}>
                <Trash2 className="size-4" /> Eliminar
            </Button>
            <ConfirmDialog open={confirmDelete} onOpenChange={setConfirmDelete}
                title={`Eliminar Instant Play #${quickPlay.id}`}
                description="Se quitará este envío del historial y se cancelarán las entregas pendientes. Si un TV ya recibió el contenido, puede terminar de reproducirlo. El archivo seguirá en tu biblioteca."
                confirmLabel="Eliminar Instant Play"
                onConfirm={() => form.delete(`${basePath}/${quickPlay.id}`, options)} />
        </div>
    );
}
