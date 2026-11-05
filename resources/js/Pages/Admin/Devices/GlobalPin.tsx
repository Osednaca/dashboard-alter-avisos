import { Head, useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { toast } from 'sonner';
import { FormField } from '@/Components/app/FormField';
import { PageHeader } from '@/Components/app/PageHeader';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { AdminLayout } from '@/Layouts/AdminLayout';

export default function GlobalPin({ configured }: { configured: boolean }) {
    const form = useForm({ pin: '', pin_confirmation: '' });

    return (
        <AdminLayout>
            <Head title="PIN global de pantallas" />
            <PageHeader eyebrow="Pantallas" title="PIN global" description="Un único PIN para todas las pantallas de todos los negocios." />
            <Card className="mt-6 max-w-2xl">
                <CardHeader><CardTitle>Acceso a Configuración en el TV</CardTitle></CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-sm text-muted">
                        {configured ? 'El PIN global está configurado.' : 'Configura el PIN global para habilitar el acceso a Configuración.'}
                        {' '}Los PIN anteriores de cada pantalla ya no son válidos. Al guardar, el nuevo PIN se aplica inmediatamente a todas las pantallas y todos los negocios.
                        {' '}Usa seis dígitos. El TV necesita conexión con el servidor para validarlo.
                    </p>
                    <form className="space-y-4" onSubmit={(event) => {
                        event.preventDefault();
                        form.put('/admin/devices/global-pin', {
                            preserveScroll: true,
                            onSuccess: () => toast.success('PIN actualizado para todas las pantallas.'),
                            onFinish: () => form.reset(),
                        });
                    }}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="Nuevo PIN global" htmlFor="global-screen-pin" error={form.errors.pin}>
                                <Input id="global-screen-pin" type="password" inputMode="numeric" autoComplete="new-password"
                                    pattern="[0-9]{6}" maxLength={6} required value={form.data.pin}
                                    onChange={(event) => form.setData('pin', event.target.value)} />
                            </FormField>
                            <FormField label="Confirmar PIN" htmlFor="global-screen-pin-confirmation" error={form.errors.pin_confirmation}>
                                <Input id="global-screen-pin-confirmation" type="password" inputMode="numeric" autoComplete="new-password"
                                    pattern="[0-9]{6}" maxLength={6} required value={form.data.pin_confirmation}
                                    onChange={(event) => form.setData('pin_confirmation', event.target.value)} />
                            </FormField>
                        </div>
                        <Button type="submit" disabled={form.processing}><KeyRound className="size-4" />Guardar PIN global</Button>
                    </form>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
