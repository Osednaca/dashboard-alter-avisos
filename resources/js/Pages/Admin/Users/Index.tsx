import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, UserCog } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ActionMenu } from '@/Components/app/ActionMenu';
import { ConfirmDialog } from '@/Components/app/ConfirmDialog';
import { DataTable, type Column } from '@/Components/app/DataTable';
import { EmptyState } from '@/Components/app/EmptyState';
import { FilterBar } from '@/Components/app/FilterBar';
import { FormField } from '@/Components/app/FormField';
import { PageHeader } from '@/Components/app/PageHeader';
import { Pagination } from '@/Components/app/Pagination';
import { StatusBadge } from '@/Components/app/StatusBadge';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { PasswordInput } from '@/Components/ui/password-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { AdminLayout } from '@/Layouts/AdminLayout';
import type { EnumValue, Option, Paginated } from '@/Types';
import { formatRelative } from '@/Utils/format';

interface UserEntity {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    avatar_url: string | null;
    initials: string;
    status: EnumValue;
    roles: string[];
    business_id: number | null;
    business_name: string | null;
    last_login_at: string | null;
    created_at: string | null;
}

const BUSINESS_ROLE = 'business-user';

const roleLabels: Record<string, string> = {
    'super-admin': 'Super administrador',
    administrator: 'Administrador',
    operator: 'Operador',
    'campaign-manager': 'Gestor de campañas',
    support: 'Soporte',
    'business-user': 'Usuario de negocio',
};

export default function UsersIndex({
    users,
    filters,
    options,
}: {
    users: Paginated<UserEntity>;
    filters: { search?: string; status?: string; role?: string };
    options: {
        statuses: Option[];
        roles: Array<{ name: string; label: string }>;
        businesses: Array<{ id: number; name: string }>;
    };
}) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<UserEntity | null>(null);
    const [deleting, setDeleting] = useState<UserEntity | null>(null);

    const form = useForm({
        name: '',
        email: '',
        job_title: '',
        status: 'active',
        password: '',
        password_confirmation: '',
        roles: [] as string[],
        business_id: '',
    });

    const isBusinessUser = form.data.roles.includes(BUSINESS_ROLE);

    const applyFilter = (patch: Record<string, string>) => {
        const next: Record<string, string> = { ...filters, ...patch };
        Object.keys(next).forEach((key) => {
            if (!next[key]) delete next[key];
        });
        router.get('/admin/users', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const openCreate = () => {
        form.reset();
        form.clearErrors();
        setEditing(null);
        setOpen(true);
    };

    const openEdit = (user: UserEntity) => {
        form.setData({
            name: user.name,
            email: user.email,
            job_title: user.job_title ?? '',
            status: user.status.value,
            password: '',
            password_confirmation: '',
            roles: user.roles,
            business_id: user.business_id ? String(user.business_id) : '',
        });
        form.clearErrors();
        setEditing(user);
        setOpen(true);
    };

    const submit = () => {
        const config = {
            onSuccess: () => {
                setOpen(false);
                setEditing(null);
                form.reset();
            },
        };
        if (editing) form.put(`/admin/users/${editing.id}`, config);
        else form.post('/admin/users', config);
    };

    const columns: Array<Column<UserEntity>> = [
        {
            key: 'name',
            header: 'Usuario',
            cell: (row) => (
                <div className="flex items-center gap-3">
                    <Avatar>
                        {row.avatar_url ? <AvatarImage src={row.avatar_url} alt={row.name} /> : null}
                        <AvatarFallback>{row.initials}</AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <p className="truncate font-medium text-fg">{row.name}</p>
                        <p className="truncate text-xs text-faint">{row.email}</p>
                    </div>
                </div>
            ),
        },
        { key: 'job', header: 'Cargo', cell: (row) => <span className="text-muted">{row.job_title ?? '—'}</span> },
        {
            key: 'roles',
            header: 'Roles',
            cell: (row) => <span className="text-muted">{row.roles.map((role) => roleLabels[role] ?? role).join(', ')}</span>,
        },
        { key: 'status', header: 'Estado', cell: (row) => <StatusBadge value={row.status} /> },
        { key: 'last_login', header: 'Último acceso', cell: (row) => <span className="text-muted">{formatRelative(row.last_login_at)}</span> },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (row) => (
                <div className="flex justify-end">
                    <ActionMenu
                        items={[
                            { label: 'Editar', icon: Pencil, onSelect: () => openEdit(row) },
                            { label: 'Eliminar', icon: Trash2, variant: 'danger', separatorBefore: true, onSelect: () => setDeleting(row) },
                        ]}
                    />
                </div>
            ),
        },
    ];

    return (
        <AdminLayout>
            <Head title="Usuarios" />

            <PageHeader
                title="Usuarios"
                description="Equipo con acceso al panel según sus roles y permisos."
                actions={
                    <Button variant="primary" size="sm" onClick={openCreate}>
                        <Plus className="size-4" />
                        Nuevo usuario
                    </Button>
                }
            />

            <div className="mt-6 rounded-card border border-line bg-card p-4">
                <FilterBar search={filters.search} onSearch={(value) => applyFilter({ search: value })} searchPlaceholder="Buscar por nombre o correo…">
                    <Select value={filters.role ?? 'all'} onValueChange={(value) => applyFilter({ role: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Rol" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos los roles</SelectItem>
                            {options.roles.map((role) => (
                                <SelectItem key={role.name} value={role.name}>
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.status ?? 'all'} onValueChange={(value) => applyFilter({ status: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-40">
                            <SelectValue placeholder="Estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos los estados</SelectItem>
                            {options.statuses.map((status) => (
                                <SelectItem key={status.value} value={status.value}>
                                    {status.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FilterBar>

                <div className="mt-4">
                    <DataTable columns={columns} rows={users.data} keyExtractor={(row) => row.id} empty={<EmptyState icon={UserCog} title="Sin usuarios" />} />
                </div>
                <Pagination paginator={users} />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Editar usuario' : 'Nuevo usuario'}</DialogTitle>
                    </DialogHeader>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField label="Nombre" error={form.errors.name} className="sm:col-span-2">
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </FormField>
                        <FormField label="Correo" error={form.errors.email}>
                            <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                        </FormField>
                        <FormField label="Cargo" error={form.errors.job_title}>
                            <Input value={form.data.job_title} onChange={(e) => form.setData('job_title', e.target.value)} />
                        </FormField>
                        <FormField label="Estado" error={form.errors.status}>
                            <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.statuses.map((status) => (
                                        <SelectItem key={status.value} value={status.value}>
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField label={editing ? 'Nueva contraseña (opcional)' : 'Contraseña'} error={form.errors.password}>
                            <PasswordInput
                                value={form.data.password}
                                autoComplete="new-password"
                                onChange={(e) => form.setData('password', e.target.value)}
                            />
                        </FormField>
                        <FormField label="Confirmar contraseña" error={form.errors.password_confirmation}>
                            <PasswordInput
                                value={form.data.password_confirmation}
                                autoComplete="new-password"
                                onChange={(e) => form.setData('password_confirmation', e.target.value)}
                            />
                        </FormField>
                        <FormField label="Roles" error={form.errors.roles} className="sm:col-span-2">
                            <div className="flex flex-wrap gap-3">
                                {options.roles.map((role) => {
                                    const checked = form.data.roles.includes(role.name);
                                    return (
                                        <label key={role.name} className="flex items-center gap-2 text-xs text-muted">
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={(value) => {
                                                    if (value) {
                                                        form.setData('roles', [...form.data.roles, role.name]);
                                                        return;
                                                    }

                                                    form.setData({
                                                        ...form.data,
                                                        roles: form.data.roles.filter((name) => name !== role.name),
                                                        business_id: role.name === BUSINESS_ROLE ? '' : form.data.business_id,
                                                    });
                                                }}
                                            />
                                            {role.label}
                                        </label>
                                    );
                                })}
                            </div>
                        </FormField>

                        {isBusinessUser ? (
                            <FormField
                                label="Negocio asignado"
                                error={form.errors.business_id}
                                hint="El usuario solo tendrá acceso a este negocio."
                                className="sm:col-span-2"
                            >
                                <Select
                                    value={form.data.business_id || undefined}
                                    onValueChange={(value) => form.setData('business_id', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona un negocio" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.businesses.map((business) => (
                                            <SelectItem key={business.id} value={String(business.id)}>
                                                {business.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        ) : null}
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setOpen(false)}>
                            Cancelar
                        </Button>
                        <Button variant="primary" onClick={submit} disabled={form.processing}>
                            {editing ? 'Guardar cambios' : 'Crear usuario'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(value) => (!value ? setDeleting(null) : null)}
                title={`Eliminar ${deleting?.name ?? ''}`}
                description="El usuario perderá acceso al panel inmediatamente."
                confirmLabel="Eliminar usuario"
                onConfirm={() => {
                    if (!deleting) return;
                    router.delete(`/admin/users/${deleting.id}`, { onSuccess: () => toast.success('Usuario eliminado.') });
                }}
            />
        </AdminLayout>
    );
}
