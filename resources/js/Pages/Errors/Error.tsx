import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import type { PageProps } from '@/Types';

const messages: Record<number, { title: string; description: string }> = {
    403: { title: 'Acceso denegado', description: 'No tienes permisos para ver esta sección.' },
    404: { title: 'No encontrado', description: 'El recurso que buscas no existe o fue movido.' },
    419: { title: 'Sesión expirada', description: 'Actualiza la página e inicia sesión de nuevo.' },
    429: { title: 'Demasiadas solicitudes', description: 'Espera un momento antes de volver a intentar.' },
    500: { title: 'Error del servidor', description: 'Algo salió mal de nuestro lado. Intenta más tarde.' },
    503: { title: 'En mantenimiento', description: 'El servicio no está disponible temporalmente.' },
};

export default function Error({ status }: { status: number }) {
    const message = messages[status] ?? messages[500];
    const user = usePage<PageProps>().props.auth.user;
    const home = user
        ? user.permissions.includes('business.dashboard.view') && !user.permissions.includes('devices.view')
            ? '/business/dashboard'
            : '/admin/dashboard'
        : '/login';

    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-canvas px-6 text-center">
            <Head title={`Error ${status}`} />
            <p className="metric text-6xl font-semibold text-accent">{status}</p>
            <div>
                <h1 className="text-lg font-semibold text-fg">{message.title}</h1>
                <p className="mt-1 text-sm text-muted">{message.description}</p>
            </div>
            <Button variant="primary" asChild>
                <Link href={home}>{user ? 'Volver al dashboard' : 'Iniciar sesión'}</Link>
            </Button>
        </div>
    );
}
