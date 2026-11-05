import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/Types';

export function usePermissions() {
    const { auth } = usePage<PageProps>().props;
    const permissions = auth.user?.permissions ?? [];
    const roles = auth.user?.roles ?? [];

    return {
        permissions,
        roles,
        can: (permission: string) => permissions.includes(permission),
        canAny: (...required: string[]) => required.some((permission) => permissions.includes(permission)),
        hasRole: (role: string) => roles.includes(role),
    };
}
