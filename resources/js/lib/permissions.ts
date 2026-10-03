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

/**
 * Human-readable friendly action labels for permissions in roles management.
 */
export const PERMISSION_LABELS: Record<string, string> = {
    // Productos
    'productos.ver': 'Ver catálogo de productos',
    'productos.crear': 'Crear nuevos productos',
    'productos.editar': 'Editar productos existentes',
    'productos.eliminar': 'Eliminar productos del catálogo',

    // Stock
    'stock_ingreso.ver': 'Ver historial de ingresos de stock',
    'stock_ingreso.crear': 'Registrar ingresos de stock',
    'stock_egreso.ver': 'Ver historial de egresos de stock',
    'stock_egreso.crear': 'Registrar egresos de stock',
    'stock_historial.ver': 'Ver kardex y auditoría de stock',
    'stock_minimo.ver': 'Ver alertas de stock mínimo',
    'deposito.gestion': 'Gestionar y configurar depósitos',

    // Proveedores
    'proveedores.ver': 'Ver listado de proveedores',
    'proveedores.crear': 'Crear nuevos proveedores',
    'proveedores.editar': 'Editar información de proveedores',
    'proveedores.eliminar': 'Eliminar proveedores',
    'proveedores.pedidos.ver': 'Ver órdenes de compra a proveedores',
    'proveedores.pedidos.crear': 'Crear órdenes de compra a proveedores',
    'proveedores.pedidos.recibir': 'Recibir mercadería de compras',
    'proveedores.pedidos.cancelar': 'Cancelar órdenes de compra',

    // Clientes
    'clientes.ver': 'Ver cartera y datos de clientes',
    'clientes.crear': 'Crear nuevos clientes',
    'clientes.editar': 'Editar datos de clientes',
    'clientes.eliminar': 'Eliminar clientes',
    'cuentas_corrientes.ver': 'Ver cuentas corrientes y saldos',
    'cobranzas.ver': 'Ver historial de cobranzas y recibos',
    'cobranzas.crear': 'Registrar cobros y emitir recibos',
    'cobranzas.anular': 'Anular recibos de cobro',

    // Pedidos
    'pedidos.ver': 'Ver listado y pedidos de clientes',
    'pedidos.crear': 'Crear nuevos pedidos de clientes',
    'pedidos.editar_estado': 'Cambiar estados logísticos de pedidos',
    'pedidos.cancelar': 'Cancelar pedidos de clientes',
    'zonas.ver': 'Ver zonas y tarifas de entrega',
    'zonas.gestionar': 'Configurar zonas de entrega',
    'repartos.ver': 'Ver repartos y hoja de ruta',
    'repartos.gestionar': 'Asignar choferes y despachar repartos',

    // Facturación ARCA
    'facturas.emitir': 'Emitir facturas y notas de crédito ARCA',
    'facturas.consultar': 'Consultar comprobantes electrónicos emitidos',
    'facturas.anular': 'Anular facturas mediante Notas de Crédito',
    'facturas.puntos_venta': 'Gestionar puntos de venta fiscales',

    // Recargos y Descuentos
    'recargos_descuentos.ver': 'Ver recargos y descuentos del comercio',
    'recargos_descuentos.gestionar': 'Gestionar recargos y descuentos del comercio',
};

export function getPermissionLabel(name: string): string {
    return PERMISSION_LABELS[name] || name;
}

