import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

/** Permission helpers backed by the shared Inertia props (see HandleInertiaRequests). */
export function usePermissions() {
    const { auth } = usePage<SharedProps>().props;
    const isSuperAdmin = auth.user?.is_super_admin === true;

    const can = (permission?: string): boolean => {
        if (!permission) {
            return true;
        }

        return isSuperAdmin || auth.permissions.includes(permission);
    };

    return { can, isSuperAdmin, roles: auth.roles };
}
