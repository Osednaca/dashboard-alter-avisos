import { Head, useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useRef } from 'react';
import { FormField } from '@/Components/app/FormField';
import { PageHeader } from '@/Components/app/PageHeader';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input, Textarea } from '@/Components/ui/input';
import { Progress } from '@/Components/ui/progress';
import { AdminLayout } from '@/Layouts/AdminLayout';

interface UpdateManifest {
    versionCode: number;
    versionName: string;
    apkUrl: string;
    sha256: string;
    forceUpdate: boolean;
    changelog: string;
}

const MAX_APK_BYTES = 128 * 1024 * 1024;
const MAX_CHANGELOG_BYTES = 16 * 1024;
const MANIFEST_URL = 'https://signage.finespublicidad.com/updates/android/latest.json';

export default function AndroidUpdates({ currentUpdate }: { currentUpdate: UpdateManifest | null }) {
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm({ apk: null as File | null, forceUpdate: false, changelog: '' });
    const changelogBytes = new TextEncoder().encode(form.data.changelog).byteLength;
    const publicationError = (form.errors as Record<string, string | undefined>).publication;

    return (
        <AdminLayout>
            <Head title="Actualizaciones Android" />
            <PageHeader eyebrow="Administración" title="Actualizaciones Android"
                description="Publica un APK firmado para actualizar las pantallas sin perder sus datos ni su sesión." />

            <div className="mt-6 grid items-start gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Publicar una versión</CardTitle></CardHeader>
                    <CardContent>
                        <form className="space-y-5" onSubmit={(event) => {
                            event.preventDefault();
                            if (form.processing || !form.data.apk || changelogBytes > MAX_CHANGELOG_BYTES) return;
                            form.post('/admin/android-updates', {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: () => {
                                    form.reset('apk');
                                    if (fileInput.current) fileInput.current.value = '';
                                },
                            });
                        }}>
                            <FormField label="APK de producción" htmlFor="android-update-apk" error={form.errors.apk}
                                hint="Selecciona un único APK firmado con la clave original. Máximo 128 MB.">
                                <Input id="android-update-apk" ref={fileInput} type="file" required
                                    accept=".apk,application/vnd.android.package-archive" disabled={form.processing}
                                    onChange={(event) => {
                                        const apk = event.target.files?.[0] ?? null;
                                        form.clearErrors('apk');
                                        if (apk && apk.size > MAX_APK_BYTES) {
                                            form.setData('apk', null);
                                            form.setError('apk', 'El APK supera el límite de 128 MB.');
                                        } else form.setData('apk', apk);
                                    }} />
                            </FormField>
                            <p className="text-xs text-muted">
                                La versión y el checksum se obtienen del archivo automáticamente.
                                Publica un código de versión superior al actual; no necesitas escribir el JSON.
                            </p>
                            <FormField label="Notas de la versión" htmlFor="android-update-changelog"
                                error={form.errors.changelog} hint={`${changelogBytes} / ${MAX_CHANGELOG_BYTES} bytes de texto.`}>
                                <Textarea id="android-update-changelog" rows={5} value={form.data.changelog}
                                    maxLength={MAX_CHANGELOG_BYTES} disabled={form.processing}
                                    placeholder="Describe los cambios que verá el operador de la pantalla."
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        form.setData('changelog', value);
                                        form.clearErrors('changelog');
                                        if (new TextEncoder().encode(value).byteLength > MAX_CHANGELOG_BYTES) {
                                            form.setError('changelog', 'Las notas superan el límite de 16 KiB.');
                                        }
                                    }} />
                            </FormField>
                            <FormField label="Actualización obligatoria" htmlFor="android-update-force" error={form.errors.forceUpdate}
                                hint="Las pantallas deberán actualizar para continuar. Android puede pedir confirmación para instalar.">
                                <Checkbox id="android-update-force" checked={form.data.forceUpdate} disabled={form.processing}
                                    onCheckedChange={(checked) => form.setData('forceUpdate', checked === true)} />
                            </FormField>
                            {publicationError ? <p role="alert" className="text-sm text-danger">{publicationError}</p> : null}
                            {form.processing ? (
                                <div className="space-y-2" role="status" aria-live="polite">
                                    <Progress value={form.progress?.percentage ?? 0} aria-label="Subida del APK" />
                                    <p className="text-xs text-muted">
                                        {form.progress?.percentage === 100 ? 'Validando y publicando…'
                                            : `Subiendo APK: ${form.progress?.percentage ?? 0} %`}
                                    </p>
                                </div>
                            ) : null}
                            <Button type="submit" disabled={form.processing || !form.data.apk || changelogBytes > MAX_CHANGELOG_BYTES}>
                                <Upload className="size-4" />{form.processing ? 'Publicando…' : 'Publicar actualización'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Versión publicada</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        {currentUpdate ? (
                            <>
                                <dl className="grid grid-cols-2 gap-4 text-sm">
                                    <div><dt className="text-xs text-muted">Versión</dt><dd className="mt-1 font-semibold">{currentUpdate.versionName}</dd></div>
                                    <div><dt className="text-xs text-muted">Código</dt><dd className="mt-1 font-semibold">{currentUpdate.versionCode}</dd></div>
                                    <div className="col-span-2"><dt className="text-xs text-muted">Instalación</dt>
                                        <dd className="mt-1">{currentUpdate.forceUpdate ? 'Obligatoria' : 'Opcional'}</dd></div>
                                    <div className="col-span-2"><dt className="text-xs text-muted">SHA-256</dt>
                                        <dd className="mt-1 break-all font-mono text-xs">{currentUpdate.sha256}</dd></div>
                                </dl>
                                <div className="flex flex-wrap gap-4 text-sm">
                                    <a className="text-accent hover:underline" href={MANIFEST_URL} target="_blank" rel="noopener noreferrer">Ver manifiesto JSON</a>
                                    <a className="text-accent hover:underline" href={currentUpdate.apkUrl} target="_blank" rel="noopener noreferrer">Descargar APK publicado</a>
                                </div>
                                {currentUpdate.changelog ? <p className="max-h-48 overflow-y-auto whitespace-pre-wrap text-sm text-muted">{currentUpdate.changelog}</p> : null}
                            </>
                        ) : <p className="text-sm text-muted">Todavía no hay una actualización publicada.</p>}
                        <p className="text-xs text-faint">
                            El APK se publica primero y el manifiesto después. Las pantallas detectarán
                            la versión en su próxima consulta de actualizaciones.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
