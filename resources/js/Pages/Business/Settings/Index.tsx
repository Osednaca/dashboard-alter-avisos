import { Head, router, useForm } from '@inertiajs/react';
import { Building2, MapPin, Pencil, Plus, Save, ShieldCheck, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/Components/app/ConfirmDialog';
import { FormField, FormSection } from '@/Components/app/FormField';
import { PageHeader } from '@/Components/app/PageHeader';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { usePermissions } from '@/Hooks/usePermissions';
import { BusinessLayout } from '@/Layouts/BusinessLayout';
import type { BusinessProfile, EnumValue } from '@/Types';

interface LocationRow {
    id: number;
    name: string;
    city: string;
    state: string | null;
    address: string | null;
    status: EnumValue;
    devices_count: number;
}

interface BusinessUserRow {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    role: string;
    is_primary: boolean;
}

export default function BusinessSettings({
    business,
    locations,
    users,
    locationStatuses,
}: {
    business: BusinessProfile;
    locations: LocationRow[];
    users: BusinessUserRow[];
    locationStatuses: Array<{ value: string; label: string }>;
}) {
    const { can } = usePermissions();
    const canManage = can('business.settings.manage');

    const profileForm = useForm({
        name: business.name,
        contact_name: business.contact_name ?? '',
        contact_email: business.contact_email ?? '',
        contact_phone: business.contact_phone ?? '',
    });

    const [locationOpen, setLocationOpen] = useState(false);
    const [editingLocation, setEditingLocation] = useState<LocationRow | null>(null);
    const [deletingLocation, setDeletingLocation] = useState<LocationRow | null>(null);

    const locationForm = useForm({
        name: '',
        city: '',
        state: '',
        country: 'Colombia',
        address: '',
        timezone: business.timezone,
        status: 'active',
    });

    const openCreateLocation = () => {
        locationForm.reset();
        locationForm.clearErrors();
        setEditingLocation(null);
        setLocationOpen(true);
    };

    const openEditLocation = (location: LocationRow) => {
        locationForm.setData({
            name: location.name,
            city: location.city,
            state: location.state ?? '',
            country: 'Colombia',
            address: location.address ?? '',
            timezone: business.timezone,
            status: location.status.value,
        });
        locationForm.clearErrors();
        setEditingLocation(location);
        setLocationOpen(true);
    };

    const submitLocation = () => {
        const config = { preserveScroll: true, onSuccess: () => setLocationOpen(false) };
        if (editingLocation) {
            locationForm.put(`/business/settings/locations/${editingLocation.id}`, config);
        } else {
            locationForm.post('/business/settings/locations', config);
        }
    };

    const saveProfile = () => {
        profileForm.put('/business/settings', { preserveScroll: true });
    };

    return (
        <BusinessLayout>
            <Head title="Configuración" />

            <PageHeader
                eyebrow="Cuenta"
                title="Configuración"
                description="Información del negocio, ubicaciones y usuarios."
                actions={
                    canManage ? (
                        <Button variant="primary" size="sm" onClick={saveProfile} disabled={profileForm.processing}>
                            <Save className="size-4" />
                            Guardar cambios
                        </Button>
                    ) : null
                }
            />

            <Tabs defaultValue="business" className="mt-6">
                <TabsList>
                    <TabsTrigger value="business">Negocio</TabsTrigger>
                    <TabsTrigger value="locations">Ubicaciones</TabsTrigger>
                    <TabsTrigger value="users">Usuarios</TabsTrigger>
                </TabsList>

                <TabsContent value="business" className="mt-4 space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Información del negocio</CardTitle>
                            <Badge tone="neutral">
                                <ShieldCheck className="size-3" /> La publicidad la controla la plataforma
                            </Badge>
                        </CardHeader>
                        <CardContent>
                            <FormSection title="Datos generales" columns={2}>
                                <FormField label="Nombre" error={profileForm.errors.name}>
                                    <Input
                                        value={profileForm.data.name}
                                        disabled={!canManage}
                                        onChange={(event) => profileForm.setData('name', event.target.value)}
                                    />
                                </FormField>
                                <FormField label="Contacto" error={profileForm.errors.contact_name}>
                                    <Input
                                        value={profileForm.data.contact_name}
                                        disabled={!canManage}
                                        onChange={(event) => profileForm.setData('contact_name', event.target.value)}
                                    />
                                </FormField>
                                <FormField label="Correo de contacto" error={profileForm.errors.contact_email}>
                                    <Input
                                        type="email"
                                        value={profileForm.data.contact_email}
                                        disabled={!canManage}
                                        onChange={(event) => profileForm.setData('contact_email', event.target.value)}
                                    />
                                </FormField>
                                <FormField label="Teléfono" error={profileForm.errors.contact_phone}>
                                    <Input
                                        value={profileForm.data.contact_phone}
                                        disabled={!canManage}
                                        onChange={(event) => profileForm.setData('contact_phone', event.target.value)}
                                    />
                                </FormField>
                            </FormSection>

                        </CardContent>
                    </Card>

                </TabsContent>

                <TabsContent value="locations" className="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Ubicaciones</CardTitle>
                            {canManage ? (
                                <Button variant="primary" size="sm" onClick={openCreateLocation}>
                                    <Plus className="size-4" />
                                    Nueva ubicación
                                </Button>
                            ) : null}
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {locations.length === 0 ? (
                                <p className="rounded-control border border-dashed border-line px-4 py-8 text-center text-xs text-faint">
                                    Sin ubicaciones registradas.
                                </p>
                            ) : (
                                locations.map((location) => (
                                    <div
                                        key={location.id}
                                        className="flex items-center gap-3 rounded-control border border-line bg-surface px-3 py-2.5"
                                    >
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-control border border-line bg-card text-accent">
                                            <MapPin className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm text-fg">{location.name}</p>
                                            <p className="truncate text-[11px] text-faint">
                                                {[location.city, location.address].filter(Boolean).join(' · ')}
                                            </p>
                                        </div>
                                        <Badge tone="neutral">{location.devices_count} pantallas</Badge>
                                        <StatusBadge value={location.status} dot={false} />
                                        {canManage ? (
                                            <div className="flex items-center gap-1">
                                                <Button variant="ghost" size="icon-sm" onClick={() => openEditLocation(location)} aria-label="Editar">
                                                    <Pencil className="size-3.5" />
                                                </Button>
                                                <Button variant="ghost" size="icon-sm" onClick={() => setDeletingLocation(location)} aria-label="Eliminar">
                                                    <Trash2 className="size-3.5 text-danger" />
                                                </Button>
                                            </div>
                                        ) : null}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="users" className="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Usuarios del negocio</CardTitle>
                            <span className="flex items-center gap-1.5 text-xs text-muted">
                                <Users className="size-3.5" /> {users.length}
                            </span>
                        </CardHeader>
                        <CardContent className="px-0">
                            <div className="overflow-x-auto">
                            <table className="w-full min-w-[520px] text-sm">
                                <thead>
                                    <tr className="border-y border-line text-[11px] uppercase tracking-wider text-faint">
                                        <th className="px-5 py-2.5 text-left font-semibold">Nombre</th>
                                        <th className="px-3 py-2.5 text-left font-semibold">Correo</th>
                                        <th className="px-3 py-2.5 text-left font-semibold">Cargo</th>
                                        <th className="px-5 py-2.5 text-left font-semibold">Rol</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((user) => (
                                        <tr key={user.id} className="border-b border-line/70 last:border-0">
                                            <td className="px-5 py-3 text-fg">
                                                {user.name}
                                                {user.is_primary ? <Badge tone="accent" className="ml-2">Principal</Badge> : null}
                                            </td>
                                            <td className="px-3 py-3 text-muted">{user.email}</td>
                                            <td className="px-3 py-3 text-muted">{user.job_title ?? '—'}</td>
                                            <td className="px-5 py-3 text-muted">{user.role}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            <Dialog open={locationOpen} onOpenChange={setLocationOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingLocation ? 'Editar ubicación' : 'Nueva ubicación'}</DialogTitle>
                    </DialogHeader>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField label="Nombre" error={locationForm.errors.name} className="sm:col-span-2">
                            <Input value={locationForm.data.name} onChange={(event) => locationForm.setData('name', event.target.value)} />
                        </FormField>
                        <FormField label="Ciudad" error={locationForm.errors.city}>
                            <Input value={locationForm.data.city} onChange={(event) => locationForm.setData('city', event.target.value)} />
                        </FormField>
                        <FormField label="Departamento" error={locationForm.errors.state}>
                            <Input value={locationForm.data.state} onChange={(event) => locationForm.setData('state', event.target.value)} />
                        </FormField>
                        <FormField label="Dirección" error={locationForm.errors.address} className="sm:col-span-2">
                            <Input value={locationForm.data.address} onChange={(event) => locationForm.setData('address', event.target.value)} />
                        </FormField>
                        <FormField label="Estado" error={locationForm.errors.status}>
                            <Select value={locationForm.data.status} onValueChange={(value) => locationForm.setData('status', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {locationStatuses.map((status) => (
                                        <SelectItem key={status.value} value={status.value}>
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setLocationOpen(false)}>
                            Cancelar
                        </Button>
                        <Button variant="primary" onClick={submitLocation} disabled={locationForm.processing}>
                            {editingLocation ? 'Guardar cambios' : 'Crear ubicación'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deletingLocation}
                onOpenChange={(value) => (!value ? setDeletingLocation(null) : null)}
                title={`Eliminar ${deletingLocation?.name ?? ''}`}
                description="Las pantallas de esta ubicación quedarán sin asignar."
                confirmLabel="Eliminar ubicación"
                onConfirm={() => {
                    if (!deletingLocation) return;
                    router.delete(`/business/settings/locations/${deletingLocation.id}`, { preserveScroll: true });
                }}
            />

            <div className="mt-4 flex items-center gap-2 text-[11px] text-faint">
                <Building2 className="size-3.5" />
                La configuración global de la plataforma y la publicidad no están disponibles en el panel del negocio.
            </div>
        </BusinessLayout>
    );
}
