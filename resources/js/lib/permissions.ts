import { page } from '@inertiajs/svelte';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    tenant_id: number | null;
    is_superadmin: boolean;
}

export interface AuthTenant {
    id: number;
    name: string;
    cuit_tax_id: string;
    tax_condition: string;
    is_active: boolean;
}

export interface AuthProps {
    user: AuthUser | null;
    tenant: AuthTenant | null;
    roles: string[];
    permissions: string[];
    modules: string[];
    is_admin: boolean;
}

/**
 * Check if the authenticated user has a specific permission.
 * Superadmin has universal access; other users must have the permission in their permissions array.
 */
export function hasPermission(permission: string): boolean {
    const auth = (page.props as unknown as { auth?: AuthProps })?.auth;
    if (!auth?.user) {
        return false;
    }
    if (auth.user.is_superadmin) {
        return true;
    }

    return auth.permissions?.includes(permission) ?? false;
}

/**
 * Check if the authenticated user has ANY of the specified permissions.
 */
export function hasAnyPermission(permissions: string[]): boolean {
    const auth = (page.props as unknown as { auth?: AuthProps })?.auth;
    if (!auth?.user) {
        return false;
    }
    if (auth.user.is_superadmin) {
        return true;
    }

    return permissions.some((p) => auth.permissions?.includes(p));
}

/**
 * Check if the tenant has an active module.
 */
export function hasModule(moduleCode: string): boolean {
    const auth = (page.props as unknown as { auth?: AuthProps })?.auth;
    if (!auth) {
        return false;
    }
    if (auth.user?.is_superadmin) {
        return true;
    }

    return auth.modules?.includes(moduleCode) ?? false;
}

/**
 * Check if the user is an administrator (either tenant admin or superadmin).
 */
export function isAdmin(): boolean {
    const auth = (page.props as unknown as { auth?: AuthProps })?.auth;
    if (!auth?.user) {
        return false;
    }
    if (auth.user.is_superadmin) {
        return true;
    }

    return Boolean(auth.is_admin || auth.roles?.includes('admin'));
}
