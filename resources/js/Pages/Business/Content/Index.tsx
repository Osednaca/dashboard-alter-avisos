import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Image as ImageIcon, PlayCircle, Sparkles } from 'lucide-react';
import { MediaThumbnail } from '@/Components/app/MediaThumbnail';
import { PageHeader } from '@/Components/app/PageHeader';
import { StatCard } from '@/Components/app/StatCard';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { BusinessLayout } from '@/Layouts/BusinessLayout';
import type { BusinessProfile, ContentScheduleEntity, MediaEntity } from '@/Types';
import { formatTime } from '@/Utils/format';

export default function ContentIndex({
    business,
    schedules,
    recentMedia,
    counts,
}: {
    business: BusinessProfile;
    schedules: ContentScheduleEntity[];
    recentMedia: MediaEntity[];
    counts: { images: number; videos: number; playlists: number; scheduled: number };
}) {
    return (
        <BusinessLayout>
            <Head title="Contenido" />

            <PageHeader
                eyebrow="Contenido"
                title="Tu contenido"
                description={`Cómo se organiza el contenido de ${business.name}.`}
                actions={
                    <>
                        <Button variant="secondary" size="sm" asChild>
                            <Link href="/business/library">
                                <ImageIcon className="size-4" />
                                Biblioteca
                            </Link>
                        </Button>
                        <Button variant="primary" size="sm" asChild>
                            <Link href="/business/schedule">
                                <CalendarDays className="size-4" />
                                Programación
                            </Link>
                        </Button>
                    </>
                }
            />

            <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Imágenes" value={counts.images} icon={ImageIcon} />
                <StatCard label="Videos" value={counts.videos} icon={PlayCircle} delay={80} />
                <StatCard label="Programaciones activas" value={counts.scheduled} icon={CalendarDays} delay={240} />
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-12">
                <Card className="xl:col-span-12">
                    <CardHeader>
                        <CardTitle>Programación de hoy</CardTitle>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/business/schedule">Editar</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {schedules.length === 0 ? (
                            <p className="rounded-control border border-dashed border-line px-4 py-10 text-center text-xs text-faint">
                                Sin programaciones.
                            </p>
                        ) : (
                            schedules.map((schedule) => (
                                <div
                                    key={schedule.id}
                                    className="flex items-center gap-3 rounded-control border border-line bg-surface px-3 py-2"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-xs font-medium text-fg">{schedule.name}</p>
                                        <p className="text-[10px] text-faint">
                                            {!schedule.daily_start_time && !schedule.daily_end_time ? 'Todo el día' : `${formatTime(schedule.daily_start_time)} – ${formatTime(schedule.daily_end_time)}`}
                                            {schedule.location ? ` · ${schedule.location.name}` : ''}
                                        </p>
                                    </div>
                                    {schedule.is_active_now ? <Badge tone="positive" dot>En curso</Badge> : null}
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle>Subidas recientes</CardTitle>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/business/library">
                            <Sparkles className="size-3.5" />
                            Ver biblioteca
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                        {recentMedia.map((media) => (
                            <div key={media.id} className="space-y-1.5">
                                <MediaThumbnail media={media} />
                                <p className="truncate text-[11px] text-fg">{media.filename}</p>
                                <p className="text-[10px] text-faint">{media.type.label}</p>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>
        </BusinessLayout>
    );
}
