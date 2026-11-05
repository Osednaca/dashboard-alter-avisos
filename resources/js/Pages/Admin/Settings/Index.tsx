import { Head, router } from '@inertiajs/react';
import { Check, Save, Server, Settings2, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { PageHeader } from '@/Components/app/PageHeader';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Switch } from '@/Components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { usePermissions } from '@/Hooks/usePermissions';
import { AdminLayout } from '@/Layouts/AdminLayout';

type SettingValue = string | number | boolean | null;

interface SettingRow {
    id: number;
    key: string;
    value: SettingValue;
    group: string;
    label: string;
}

interface RoleRow {
    id: number;
    name: string;
    label: string;
    description: string | null;
    is_system: boolean;
    permissions: string[];
    users_count: number | null;
}

export default function SettingsIndex({
    settings,
    roles,
    permissionGroups,
    environment,
}: {
    settings: SettingRow[];
    roles: RoleRow[];
    permissionGroups: Record<string, Array<{ value: string; label: string }>>;
    environment: Record<string, string>;
}) {
    const { can } = usePermissions();
    const [values, setValues] = useState<Record<string, SettingValue>>(
        Object.fromEntries(settings.map((setting) => [setting.key, setting.value])),
    );
    const [rolePermissions, setRolePermissions] = useState<Record<number, string[]>>(
        Object.fromEntries(roles.map((role) => [role.id, role.permissions])),
    );

    const groups = Array.from(new Set(settings.map((setting) => setting.group)));

    const saveSettings = () => {
        router.put(
            '/admin/settings',
            {
                settings: settings.map((setting) => ({
                    key: setting.key,
                    value: values[setting.key],
                    group: setting.group,
                })),
            },
            { preserveScroll: true, onSuccess: () => toast.success('Configuración guardada.') },
        );
    };

    const saveRole = (role: RoleRow) => {
        router.put(
            `/admin/settings/roles/${role.id}/permissions`,
            { permissions: rolePermissions[role.id] ?? [] },
            { preserveScroll: true, onSuccess: () => toast.success(`Permisos de ${role.label} actualizados.`) },
        );
    };

    return (
        <AdminLayout>
            <Head title="Configuración" />

            <PageHeader
                eyebrow="Sistema"
                title="Configuración"
                description="Parámetros de la red, roles, permisos y estado del entorno."
                actions={
                    <Button variant="primary" size="sm" onClick={saveSettings} disabled={!can('system.settings')}>
                        <Save className="size-4" />
                        Guardar cambios
                    </Button>
                }
            />

            <Tabs defaultValue="general" className="mt-6">
                <TabsList>
                    <TabsTrigger value="general">General</TabsTrigger>
                    <TabsTrigger value="roles">Roles y permisos</TabsTrigger>
                    <TabsTrigger value="system">Sistema</TabsTrigger>
                </TabsList>

                <TabsContent value="general" className="mt-4 space-y-4">
                    {groups.map((group) => (
                        <Card key={group}>
                            <CardHeader>
                                <CardTitle className="capitalize">{group}</CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {settings
                                    .filter((setting) => setting.group === group)
                                    .map((setting) => (
                                        <div key={setting.key} className="space-y-1.5">
                                            <label className="block text-xs font-medium text-muted">{setting.label}</label>
                                            {typeof setting.value === 'boolean' ? (
                                                <Switch
                                                    checked={Boolean(values[setting.key])}
                                                    onCheckedChange={(checked) =>
                                                        setValues((current) => ({ ...current, [setting.key]: checked }))
                                                    }
                                                />
                                            ) : (
                                                <Input
                                                    value={String(values[setting.key] ?? '')}
                                                    onChange={(event) =>
                                                        setValues((current) => ({ ...current, [setting.key]: event.target.value }))
                                                    }
                                                    disabled={!can('system.settings')}
                                                />
                                            )}
                                            <p className="metric text-[10px] text-faint">{setting.key}</p>
                                        </div>
                                    ))}
                            </CardContent>
                        </Card>
                    ))}

                    {!can('system.settings') ? (
                        <p className="text-xs text-faint">No tienes permiso para modificar la configuración.</p>
                    ) : null}
                </TabsContent>

                <TabsContent value="roles" className="mt-4 space-y-4">
                    {roles.map((role) => (
                        <Card key={role.id}>
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <ShieldCheck className="size-4 text-accent" />
                                    <div>
                                        <CardTitle>{role.label}</CardTitle>
                                        <p className="text-[11px] text-faint">
                                            {role.users_count ?? 0} usuarios · {role.is_system ? 'rol de sistema' : 'rol personalizado'}
                                        </p>
                                    </div>
                                </div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => saveRole(role)}
                                    disabled={!can('roles.manage') || role.name === 'super-admin'}
                                >
                                    <Save className="size-3.5" />
                                    Guardar permisos
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {Object.entries(permissionGroups).map(([group, permissions]) => (
                                    <div key={group}>
                                        <p className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-faint">{group}</p>
                                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                            {permissions.map((permission) => {
                                                const checked = (rolePermissions[role.id] ?? []).includes(permission.value);
                                                const isSuperAdmin = role.name === 'super-admin';
                                                return (
                                                    <label
                                                        key={permission.value}
                                                        className="flex items-center gap-2 rounded-control border border-line bg-surface px-3 py-2 text-xs text-muted"
                                                    >
                                                        <Checkbox
                                                            checked={checked || isSuperAdmin}
                                                            disabled={isSuperAdmin || !can('roles.manage')}
                                                            onCheckedChange={(value) =>
                                                                setRolePermissions((current) => {
                                                                    const list = current[role.id] ?? [];
                                                                    return {
                                                                        ...current,
                                                                        [role.id]: value
                                                                            ? [...list, permission.value]
                                                                            : list.filter((item) => item !== permission.value),
                                                                    };
                                                                })
                                                            }
                                                        />
                                                        <span className="flex-1 text-fg">{permission.label}</span>
                                                        {checked ? <Check className="size-3 text-positive" /> : null}
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </TabsContent>

                <TabsContent value="system" className="mt-4">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Server className="size-4 text-info" />
                                <CardTitle>Estado del entorno</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {Object.entries(environment).map(([key, value]) => (
                                <div key={key} className="flex items-center justify-between rounded-control border border-line bg-surface px-3 py-2">
                                    <span className="text-xs text-muted">{key.replace(/_/g, ' ')}</span>
                                    <span className="metric text-xs text-fg">{value}</span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card className="mt-4">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Settings2 className="size-4 text-accent" />
                                <CardTitle>Servicios</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2 text-xs text-muted">
                            <p>· Los manifest se generan por dispositivo y se versionan con checksum.</p>
                            <p>· Los eventos de reproducción se agregan cada noche en tablas diarias.</p>
                            <p>· Las actualizaciones en tiempo real usan Laravel Reverb; la app también consulta por sondeo.</p>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </AdminLayout>
    );
}
