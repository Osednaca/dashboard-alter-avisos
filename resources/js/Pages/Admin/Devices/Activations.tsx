import { Head, router, useForm } from '@inertiajs/react';
import { KeyRound, MapPin, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ActionMenu } from '@/Components/app/ActionMenu';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { FormField } from '@/Components/app/FormField';
import { PageHeader } from '@/Components/app/PageHeader';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { EnumValue } from '@/Types';
import { formatRelative } from '@/Utils/format';

interface ActivationEntity {
    id: number;
    code: string;
    status: EnumValue;
    device_uuid: string | null;
    device_name: string | null;
    business: { id: number; name: string } | null;
    location: { id: number; name: string } | null;
    app_version: string | null;
    ip_address: string | null;
    expires_at: string | null;
    claimed_at: string | null;
    created_at: string | null;
}

interface BusinessWithLocations {
    id: number;
    name: string;
    locations: Array<{ id: number; name: string }>;
}

export default function Activations({ activations, businesses }: { activations: ActivationEntity[]; businesses: BusinessWithLocations[] }) {
    const [selected, setSelected] = useState<ActivationEntity | null>(null);

    const form = useForm({ business_id: '', location_id: '', device_name: '' });

    const openAssign = (activation: ActivationEntity) => {
        form.setData({
            business_id: activation.business ? String(activation.business.id) : '',
            location_id: activation.location ? String(activation.location.id) : '',
            device_name: activation.device_name ?? '',
        });
        form.clearErrors();
        setSelected(activation);
    };

    const locations = businesses.find((business) => String(business.id) === form.data.business_id)?.locations ?? [];

    const columns: Array<Column<ActivationEntity>> = [
        {
            key: 'code',
            header: 'Código',
            cell: (row) => (
                <span className="metric rounded-control border border-accent/30 bg-accent/10 px-2 py-1 text-sm font-semibold tracking-[0.2em] text-accent">
                    {row.code}
                </span>
            ),
        },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        {
            key: 'business',
            header: 'Asignación',
            cell: (row) =>
                row.business ? (
                    <span className="text-muted">
                        {row.business.name}
                        {row.location ? <span className="text-faint"> · {row.location.name}</span> : null}
                    </span>
                ) : (
                    <span className="text-faint">Sin asignar</span>
                ),
        },
        { key: 'uuid', header: 'UUID', cell: (row) => <span className="metric text-[11px] text-faint">{row.device_uuid?.slice(0, 13) ?? '—'}…</span> },
        { key: 'app', header: 'App', cell: (row) => <span className="metric text-muted">{row.app_version ?? '—'}</span> },
        { key: 'expires', header: 'Expira', cell: (row) => <span className="text-muted">{formatRelative(row.expires_at)}</span> },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (row) => (
                <div className="flex justify-end">
                    <ActionMenu
                        items={[
                            { label: 'Asignar a negocio', icon: MapPin, onSelect: () => openAssign(row), hidden: row.status.value === 'claimed' },
                            {
                                label: 'Revocar código',
                                icon: Trash2,
                                variant: 'danger',
                                separatorBefore: true,
                                hidden: row.status.value === 'claimed' || row.status.value === 'revoked',
                                onSelect: () =>
                                    router.post(`/admin/activations/${row.id}/revoke`, {}, {
                                        preserveScroll: true,
                                        onSuccess: () => toast.success('Código revocado.'),
                                    }),
                            },
                        ]}
                    />
                </div>
            ),
        },
    ];

    return (
        <AdminLayout>
            <Head title="Activaciones" />

            <PageHeader
                title="Activaciones de pantallas"
                description="Códigos generados por las apps Android TV pendientes de asignación."
                actions={
                    <Button variant="secondary" size="sm" asChild>
                        <a href="/admin/devices">Ver pantallas</a>
                    </Button>
                }
            />

            <div className="mt-4 rounded-card border border-line bg-surface p-4 text-xs text-muted">
                <p className="flex items-center gap-2">
                    <KeyRound className="size-3.5 text-accent" />
                    Flujo: la app solicita un código → el administrador lo asigna a un negocio y ubicación → la pantalla
                    confirma y recibe su token.
                </p>
            </div>

            <Card className="mt-4">
                <CardContent className="pt-4">
                    <DataTable
                        columns={columns}
                        rows={activations}
                        keyExtractor={(row) => row.id}
                        empty={<EmptyState icon={KeyRound} title="Sin solicitudes de activación" description="Las nuevas pantallas aparecerán aquí al conectarse." />}
                    />
                </CardContent>
            </Card>

            <Dialog open={!!selected} onOpenChange={(open) => (!open ? setSelected(null) : null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Asignar código {selected?.code}</DialogTitle>
                    </DialogHeader>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField label="Negocio" error={form.errors.business_id} className="sm:col-span-2">
                            <Select
                                value={form.data.business_id || undefined}
                                onValueChange={(value) => form.setData({ ...form.data, business_id: value, location_id: '' })}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecciona un negocio" />
                                </SelectTrigger>
                                <SelectContent>
                                    {businesses.map((business) => (
                                        <SelectItem key={business.id} value={String(business.id)}>
                                            {business.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField label="Ubicación" error={form.errors.location_id}>
                            <Select
                                value={form.data.location_id || undefined}
                                onValueChange={(value) => form.setData('location_id', value)}
                                disabled={!locations.length}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecciona ubicación" />
                                </SelectTrigger>
                                <SelectContent>
                                    {locations.map((location) => (
                                        <SelectItem key={location.id} value={String(location.id)}>
                                            {location.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField label="Nombre de la pantalla" error={form.errors.device_name}>
                            <Input value={form.data.device_name} onChange={(e) => form.setData('device_name', e.target.value)} placeholder="Pantalla principal" />
                        </FormField>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setSelected(null)}>
                            Cancelar
                        </Button>
                        <Button
                            variant="primary"
                            disabled={form.processing}
                            onClick={() => {
                                if (!selected) return;
                                form.post(`/admin/activations/${selected.id}/assign`, {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        toast.success('Código asignado.');
                                        setSelected(null);
                                    },
                                });
                            }}
                        >
                            <Plus className="size-4" />
                            Asignar código
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
