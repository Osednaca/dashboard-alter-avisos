import { Head, router, usePage } from '@inertiajs/react';
import { CalendarDays, CalendarPlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from '@/Components/app/ConfirmDialog';
import { EmptyState } from '@/Components/app/EmptyState';
import { PageHeader } from '@/Components/app/PageHeader';
import { ScheduleCard } from '@/Components/app/ScheduleCard';
import { ScheduleComposer, type LegacyScheduleContent } from '@/Components/app/ScheduleComposer';
import { Button } from '@/Components/ui/button';
import { usePermissions } from '@/Hooks/usePermissions';
import { BusinessLayout } from '@/Layouts/BusinessLayout';
import type { ContentScheduleEntity, MediaEntity, Option } from '@/Types';

type Editor = { key: string; schedule: ContentScheduleEntity | null; imported?: LegacyScheduleContent; selectedMedia?: MediaEntity[] };

export default function ScheduleIndex({ schedules, availableMedia, legacyPlaylists, transitions, locations, selectedMedia = [] }: {
    schedules: ContentScheduleEntity[];
    availableMedia: MediaEntity[];
    selectedMedia?: MediaEntity[];
    legacyPlaylists: LegacyScheduleContent[];
    transitions: Option[];
    locations: Array<{ id: number; name: string; city: string }>;
}) {
    const { can } = usePermissions();
    const { url } = usePage();
    const canManage = can('business.schedules.manage') && can('business.playlists.manage') && can('business.playlists.view') && can('business.media.view');
    const [editor, setEditor] = useState<Editor | null>(null);
    const [deleting, setDeleting] = useState<ContentScheduleEntity | null>(null);
    // Legacy bookmarks open this same composer; no second playlist editor survives.
    useEffect(() => {
        if (!canManage) return;
        const params = new URL(url, window.location.origin).searchParams;
        const schedule = schedules.find((entry) => String(entry.id) === params.get('edit'));
        const imported = legacyPlaylists.find((entry) => String(entry.id) === params.get('import'));
        if (schedule) setEditor({ key: `edit-${schedule.id}`, schedule });
        else if (imported) setEditor({ key: `import-${imported.id}`, schedule: null, imported });
        else if (selectedMedia.length > 0) setEditor({ key: `library-${url}`, schedule: null, selectedMedia });
        // Follow URL navigation only. Fresh props after Save must not reopen the editor.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url, canManage]);

    return (
        <BusinessLayout>
            <Head title="Programación" />
            <PageHeader title="Programación" description="Elige qué mostrar, en qué orden y cuándo. Todo en un solo lugar."
                actions={canManage && !editor ? <Button variant="primary" size="sm" onClick={() => setEditor({ key: `new-${Date.now()}`, schedule: null })}><CalendarPlus className="size-4" />Nueva programación</Button> : null} />
            {editor && canManage ? (
                <ScheduleComposer key={editor.key} schedule={editor.schedule} imported={editor.imported} selectedMedia={editor.selectedMedia} availableMedia={availableMedia} legacyPlaylists={legacyPlaylists} transitions={transitions} locations={locations} canUpload={can('business.media.upload')} onClose={() => setEditor(null)} />
            ) : (
                <div className="mt-6 space-y-3">
                    {schedules.length === 0 ? <EmptyState icon={CalendarDays} title="Sin programaciones" description="Prepara tus imágenes y videos, el orden y los horarios en una sola programación." /> : schedules.map((schedule) => (
                        <ScheduleCard key={schedule.id} schedule={schedule} canManage={canManage} onEdit={(entry) => setEditor({ key: `edit-${entry.id}`, schedule: entry })} onDelete={setDeleting} />
                    ))}
                    {!canManage ? <p className="text-sm text-muted">Tu acceso permite consultar la programación. Solicita permisos de programación, contenido y biblioteca para editarla.</p> : null}
                </div>
            )}
            <ConfirmDialog open={!!deleting} onOpenChange={(value) => { if (!value) setDeleting(null); }} title={`Eliminar ${deleting?.name ?? ''}`} description="La programación dejará de reproducirse. Los archivos seguirán en la biblioteca." confirmLabel="Eliminar" onConfirm={() => {
                if (!deleting) return;
                router.delete(`/business/schedule/${deleting.id}`, { preserveScroll: true, onSuccess: () => setDeleting(null) });
            }} />
        </BusinessLayout>
    );
}
