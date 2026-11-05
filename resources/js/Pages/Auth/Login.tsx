import { Head, useForm, usePage } from '@inertiajs/react';
import { Loader2, Lock, Mail } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { PasswordInput } from '@/Components/ui/password-input';
import { Label } from '@/Components/ui/label';
import { BrandLogo } from '@/Components/app/BrandLogo';
import type { PageProps } from '@/Types';

export default function Login({ status }: { status?: string }) {
    const { app } = usePage<PageProps>().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-canvas px-4">
            <Head title="Iniciar sesión" />

            <div
                aria-hidden
                className="pointer-events-none absolute -top-32 left-1/2 h-[420px] w-[720px] -translate-x-1/2 rounded-full bg-accent/10 blur-[120px]"
            />

            <div className="relative grid w-full max-w-5xl overflow-hidden rounded-panel border border-line bg-card shadow-pop lg:grid-cols-2">
                <div className="hidden flex-col justify-between border-r border-line bg-surface p-10 lg:flex">
                    <div className="flex items-center gap-3">
                        <BrandLogo decorative className="size-12" />
                        <div>
                            <p className="text-sm font-semibold text-fg">{app.name}</p>
                            <p className="text-[11px] uppercase tracking-wider text-faint">Red de pantallas</p>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <p className="metric text-5xl font-semibold leading-none text-fg"></p>
                        <p className="max-w-xs text-sm text-muted">
                            Pantallas activas reportando en tiempo real en restaurantes, gimnasios,
                            clínicas y comercios.
                        </p>
                        <div className="flex flex-wrap gap-2 text-[11px] text-faint">
                            <span className="rounded-full border border-line px-3 py-1">Bogotá</span>
                            <span className="rounded-full border border-line px-3 py-1">Medellín</span>
                            <span className="rounded-full border border-line px-3 py-1">Cali</span>
                            <span className="rounded-full border border-line px-3 py-1">Barranquilla</span>
                        </div>
                    </div>

                    <p className="text-[11px] uppercase leading-relaxed tracking-[0.18em] text-faint">
                        Negocios
                        <br />
                        Pantallas
                        <br />
                        Audiencias
                    </p>
                </div>

                <div className="p-8 sm:p-10">
                    <div className="mb-6 flex items-center gap-3 lg:hidden">
                        <BrandLogo decorative className="size-12" />
                        <span className="text-lg font-semibold text-fg">{app.name}</span>
                    </div>
                    <h1 className="text-xl font-semibold tracking-tight text-fg">Inicia sesión</h1>
                    <p className="mt-1 text-sm text-muted">
                        Accede al panel de administración de la red publicitaria.
                    </p>

                    {status ? (
                        <p className="mt-4 rounded-control border border-positive/25 bg-positive/10 px-3 py-2 text-xs text-positive">
                            {status}
                        </p>
                    ) : null}

                    <form onSubmit={submit} className="mt-6 space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="email">Correo electrónico</Label>
                            <div className="relative">
                                <Mail className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-faint" />
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    autoComplete="username"
                                    autoFocus
                                    onChange={(event) => setData('email', event.target.value)}
                                    className="pl-9"
                                    placeholder="Ingrese su correo electrónico"
                                />
                            </div>
                            {errors.email ? <p className="text-xs text-danger">{errors.email}</p> : null}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="password">Contraseña</Label>
                            <div className="relative">
                                <Lock className="pointer-events-none absolute left-3 top-1/2 z-10 size-4 -translate-y-1/2 text-faint" />
                                <PasswordInput
                                    id="password"
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    className="pl-9"
                                    placeholder="Ingrese su contraseña"
                                />
                            </div>
                            {errors.password ? <p className="text-xs text-danger">{errors.password}</p> : null}
                        </div>

                        <label className="flex items-center gap-2 text-xs text-muted">
                            <input
                                type="checkbox"
                                checked={data.remember}
                                onChange={(event) => setData('remember', event.target.checked)}
                                className="size-3.5 rounded border-line-strong bg-inset accent-accent"
                            />
                            Mantener sesión iniciada
                        </label>

                        <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                            {processing ? <Loader2 className="size-4 animate-spin" /> : null}
                            Entrar al panel
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    );
}
