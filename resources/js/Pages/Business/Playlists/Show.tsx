import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Copy,
    ListVideo,
    Pencil,
    Play,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ConfirmDialog } from '@/Components/app/ConfirmDialog';
import { EmptyState } from '@/Components/app/EmptyState';
import { MediaPicker } from '@/Components/app/MediaPicker';
import { PageHeader } from '@/Components/app/PageHeader';
import { PlaylistItemRow } from '@/Components/app/PlaylistItemRow';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { usePermissions } from '@/Hooks/usePermissions';
import { BusinessLayout } from '@/Layouts/BusinessLayout';
import type { MediaEntity, Option, PlaylistItemEntity, PlaylistSummary } from '@/Types';
import { formatDuration } from '@/Utils/format';

export default function PlaylistShow({
    playlist,
    items,
    availableMedia,
    transitions,
}: {
    playlist: PlaylistSummary;
    items: PlaylistItemEntity[];
    availableMedia: MediaEntity[];
    transitions: Option[];
}) {
    const { can } = usePermissions();
    const canManage = can('business.playlists.manage');

    const [localItems, setLocalItems] = useState(items);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [draggingId, setDraggingId] = useState<number | null>(null);
    const [previewIndex, setPreviewIndex] = useState(0);
    const [renaming, setRenaming] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const renameForm = useForm({ name: playlist.name, status: playlist.status.value });

    useEffect(() => setLocalItems(items), [items]);

    useEffect(() => {
        if (localItems.length < 2) {
            setPreviewIndex(0);
            return;
        }
        const current = localItems[Math.min(previewIndex, localItems.length - 1)];
        const timer = setTimeout(() => {
            setPreviewIndex((index) => (index + 1) % localItems.length);
        }, Math.max(3, current?.duration ?? 8) * 1000);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [previewIndex, localItems]);

    const totalSeconds = useMemo(() => localItems.reduce((total, item) => total + item.duration, 0), [localItems]);
    const previewItem = localItems[Math.min(previewIndex, Math.max(0, localItems.length - 1))] ?? null;

    const persistOrder = (list: PlaylistItemEntity[]) => {
        router.put(
            `/business/playlists/${playlist.id}/items/reorder`,
            { order: list.map((item) => item.id) },
            { preserveScroll: true, preserveState: true },
        );
    };

    const move = (id: number, direction: -1 | 1) => {
        const list = [...localItems];
        const index = list.findIndex((item) => item.id === id);
        const target = index + direction;
        if (index < 0 || target < 0 || target >= list.length) return;
        [list[index], list[target]] = [list[target], list[index]];
        setLocalItems(list);
        persistOrder(list);
    };

    const dropOn = (targetId: number) => {
        if (!draggingId || draggingId === targetId) {
            setDraggingId(null);
            return;
        }
        const list = [...localItems];
        const from = list.findIndex((item) => item.id === draggingId);
        const to = list.findIndex((item) => item.id === targetId);
        const [moved] = list.splice(from, 1);
        list.splice(to, 0, moved);
        setLocalItems(list);
        setDraggingId(null);
        persistOrder(list);
    };

    const addMedia = (media: MediaEntity) => {
        setPickerOpen(false);
        router.post(
            `/business/playlists/${playlist.id}/items`,
            { media_asset_id: media.id, duration: media.duration ?? 10, transition: 'fade' },
            { preserveScroll: true, preserveState: true },
        );
    };

    const updateItem = (id: number, data: { duration: number; transition: string }) => {
        router.put(`/business/playlists/${playlist.id}/items/${id}`, data, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const removeItem = (id: number) => {
        setLocalItems((list) => list.filter((item) => item.id !== id));
        router.delete(`/business/playlists/${playlist.id}/items/${id}`, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <BusinessLayout>
            <Head title={playlist.name} />

            <PageHeader
                eyebrow="Playlist"
                title={playlist.name}
                description={`${localItems.length} elementos · ${formatDuration(totalSeconds)} por ciclo`}
                actions={
                    <>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/business/playlists">
                                <ArrowLeft className="size-4" />
                                Volver
                            </Link>
                        </Button>
                        {canManage ? (
                            <>
                                <Button variant="secondary" size="sm" onClick={() => setRenaming(true)}>
                                    <Pencil className="size-4" />
                                    Renombrar
                                </Button>
                                <Button variant="secondary" size="sm" onClick={() => router.post(`/business/playlists/${playlist.id}/duplicate`, {}, { preserveScroll: true })}>
                                    <Copy className="size-4" />
                                    Duplicar
                                </Button>
                                <Button variant="primary" size="sm" onClick={() => setPickerOpen(true)}>
                                    <Plus className="size-4" />
                                    Agregar contenido
                                </Button>
                            </>
                        ) : null}
                    </>
                }
            />

            <div className="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-12">
                <div className="xl:col-span-8">
                    <Card>
                        <CardHeader>
                            <CardTitle>Elementos de la playlist</CardTitle>
                            <div className="flex items-center gap-2">
                                <Badge tone="neutral">{localItems.length} elementos</Badge>
                                <Badge tone="accent">{formatDuration(totalSeconds)}</Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {localItems.length === 0 ? (
                                <EmptyState
                                    icon={ListVideo}
                                    title="Playlist vacía"
                                    description="Agrega contenido de tu biblioteca para comenzar."
                                    action={
                                        canManage ? (
                                            <Button variant="primary" size="sm" onClick={() => setPickerOpen(true)}>
                                                <Plus className="size-4" />
                                                Agregar contenido
                                            </Button>
                                        ) : null
                                    }
                                />
                            ) : (
                                localItems.map((item, index) => (
                                    <PlaylistItemRow
                                        key={item.id}
                                        item={item}
                                        index={index}
                                        total={localItems.length}
                                        transitions={transitions}
                                        canManage={canManage}
                                        onUpdate={updateItem}
                                        onRemove={removeItem}
                                        onMove={move}
                                        dragging={draggingId === item.id}
                                        onDragStart={setDraggingId}
                                        onDragOver={(event) => event.preventDefault()}
                                        onDrop={dropOn}
                                    />
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="xl:col-span-4">
                    <div className="space-y-4 xl:sticky xl:top-24">
                        <Card>
                            <CardHeader>
                                <CardTitle>Vista previa</CardTitle>
                                <span className="inline-flex items-center gap-1.5 text-xs text-accent">
                                    <Play className="size-3" />
                                    {previewItem ? `Elemento ${previewIndex + 1} de ${localItems.length}` : 'Sin contenido'}
                                </span>
                            </CardHeader>
                            <CardContent>
                                <div className="rounded-[20px] border border-line-strong bg-[#04080d] p-2.5 shadow-float">
                                    <div className="relative flex aspect-video items-center justify-center overflow-hidden rounded-[12px] bg-black">
                                        {previewItem?.media ? (
                                            previewItem.media.type.value === 'video' ? (
                                                <video
                                                    key={previewItem.media.url}
                                                    src={previewItem.media.url}
                                                    className="size-full object-cover"
                                                    muted
                                                    autoPlay
                                                    loop
                                                    playsInline
                                                />
                                            ) : (
                                                <img
                                                    key={previewItem.media.url}
                                                    src={previewItem.media.url}
                                                    alt={previewItem.media.filename}
                                                    className="size-full object-cover"
                                                />
                                            )
                                        ) : (
                                            <span className="text-[10px] text-white/40">Sin contenido</span>
                                        )}
                                    </div>
                                    <div className="mx-auto mt-2 h-1 w-16 rounded-full bg-line-strong" />
                                </div>
                                {previewItem?.media ? (
                                    <p className="mt-3 truncate text-center text-xs text-muted">
                                        {previewItem.media.filename}
                                    </p>
                                ) : null}
                            </CardContent>
                        </Card>

                        {canManage ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Ajustes</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="w-full"
                                        onClick={() => setRenaming(true)}
                                    >
                                        <Save className="size-4" />
                                        Editar nombre y estado
                                    </Button>
                                    <Button
                                        variant="danger"
                                        size="sm"
                                        className="w-full"
                                        onClick={() => setDeleting(true)}
                                    >
                                        <Trash2 className="size-4" />
                                        Eliminar playlist
                                    </Button>
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>
                </div>
            </div>

            <MediaPicker
                open={pickerOpen}
                onOpenChange={setPickerOpen}
                media={availableMedia}
                selectedIds={localItems.map((item) => item.media?.id ?? 0)}
                onSelect={addMedia}
                title="Agregar contenido a la playlist"
            />

            <Dialog open={renaming} onOpenChange={setRenaming}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Editar playlist</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-3">
                        <Input
                            value={renameForm.data.name}
                            onChange={(event) => renameForm.setData('name', event.target.value)}
                            placeholder="Nombre de la playlist"
                        />
                        {renameForm.errors.name ? (
                            <p className="text-xs text-danger">{renameForm.errors.name}</p>
                        ) : null}
                        <Select value={renameForm.data.status} onValueChange={(value) => renameForm.setData('status', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="draft">Borrador</SelectItem>
                                <SelectItem value="active">Activa</SelectItem>
                                <SelectItem value="archived">Archivada</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setRenaming(false)}>
                            Cancelar
                        </Button>
                        <Button
                            variant="primary"
                            disabled={renameForm.processing}
                            onClick={() =>
                                renameForm.put(`/business/playlists/${playlist.id}`, {
                                    preserveScroll: true,
                                    onSuccess: () => setRenaming(false),
                                })
                            }
                        >
                            Guardar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting}
                onOpenChange={setDeleting}
                title={`Eliminar ${playlist.name}`}
                description="Se eliminará la playlist y sus programaciones asociadas."
                confirmLabel="Eliminar playlist"
                onConfirm={() => router.delete(`/business/playlists/${playlist.id}`)}
            />
        </BusinessLayout>
    );
}
