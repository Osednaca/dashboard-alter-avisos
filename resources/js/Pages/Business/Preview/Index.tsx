import { Head, router, usePoll } from '@inertiajs/react';
import { MonitorPlay, Sparkles } from 'lucide-react';
import { BusinessScreenPreview, type BusinessPreviewData } from '@/Components/app/BusinessScreenPreview';
import { EmptyState } from '@/Components/app/EmptyState';
import { PageHeader } from '@/Components/app/PageHeader';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { BusinessLayout } from '@/Layouts/BusinessLayout';
import type { DeviceEntity } from '@/Types';
import { cn } from '@/Utils/cn';

export default function PreviewIndex({
    devices,
    preview,
    selectedDeviceId,
}: {
    devices: DeviceEntity[];
    preview: BusinessPreviewData | null;
    selectedDeviceId: number | null;
}) {
    usePoll(3000, { only: ['preview'] });
    const select = (id: number) => {
        router.get('/business/preview', { device: id }, { preserveState: true, preserveScroll: true, only: ['preview', 'selectedDeviceId'] });
    };

    return (
        <BusinessLayout>
            <Head title="Vista previa" />

            <PageHeader
                eyebrow="Pantallas"
                title="Vista previa de pantallas"
                description="Estado y contenido reportados por la TV, con actualización cada tres segundos."
            />

            <div className="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-12">
                <Card className="xl:col-span-8">
                    <CardHeader>
                        <CardTitle>Pantalla seleccionada</CardTitle>
                        {preview ? (
                            <span className="inline-flex items-center gap-1.5 text-xs text-muted">
                                <Sparkles className="size-3 text-accent" /> {preview.layout?.ratio ?? '—'}
                            </span>
                        ) : null}
                    </CardHeader>
                    <CardContent>
                        {preview ? (
                            <BusinessScreenPreview preview={preview} />
                        ) : (
                            <EmptyState icon={MonitorPlay} title="Sin pantallas" description="Agrega una pantalla para ver su vista previa." />
                        )}
                    </CardContent>
                </Card>

                <Card className="xl:col-span-4">
                    <CardHeader>
                        <CardTitle>Pantallas</CardTitle>
                        <span className="text-xs text-muted">{devices.length} en total</span>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {devices.map((device) => (
                            <button
                                key={device.id}
                                type="button"
                                onClick={() => select(device.id)}
                                className={cn(
                                    'flex w-full items-center gap-3 rounded-control border px-3 py-2.5 text-left transition-colors',
                                    device.id === selectedDeviceId
                                        ? 'border-accent/50 bg-accent/10'
                                        : 'border-line bg-surface hover:border-line-strong',
                                )}
                            >
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-control border border-line bg-card text-accent">
                                    <MonitorPlay className="size-4" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm text-fg">{device.name}</span>
                                    <span className="block truncate text-[11px] text-faint">
                                        {device.location?.name ?? 'Sin ubicación'}
                                    </span>
                                </span>
                                <StatusBadge value={device.status} dot={false} />
                            </button>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </BusinessLayout>
    );
}
