<script lang="ts">
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import { Link, page } from '@inertiajs/svelte';
    import { fade, slide } from 'svelte/transition';
    import Icon from '@iconify/svelte';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import X from 'lucide-svelte/icons/x';

    let {
        open = false,
        onClose,
    }: {
        open?: boolean;
        onClose?: () => void;
    } = $props();

    let auth = $derived(page.props.auth);
    let user = $derived(auth?.user);
    let tenant = $derived(auth?.tenant);
    let roles = $derived(auth?.roles ?? []);
    let permissions = $derived(auth?.permissions ?? []);
    let modules = $derived(auth?.modules ?? []);
    let isAdmin = $derived(auth?.is_admin || roles.includes('admin') || user?.is_superadmin);
    let currentUrl = $derived(page.url);

    // Estado del Acordeón con Unicidad
    let openModule = $state<string | null>(null);

    // Auto-expansión inicial del módulo activo al abrir el Sidebar
    $effect(() => {
        if (open) {
            const activeGroup = navItems.find((item) => item.subItems && item.subItems.length > 0 && item.active);
            openModule = activeGroup ? activeGroup.title : null;
        }
    });

    function toggleModule(moduleTitle: string) {
        openModule = openModule === moduleTitle ? null : moduleTitle;
    }

    interface SubItem {
        title: string;
        description?: string;
        href: string;
        icon: string;
        active: boolean;
    }

    interface NavItem {
        title: string;
        href: string;
        icon: string;
        active: boolean;
        subItems?: SubItem[];
    }

    let navItems = $derived.by(() => {
        const dashboardHref = user?.is_superadmin ? '/platform/dashboard' : toUrl(dashboard());
        const isDashboardActive = user?.is_superadmin
            ? currentUrl.startsWith('/platform/dashboard') || currentUrl === '/dashboard' || currentUrl === '/'
            : currentUrl === '/dashboard' || currentUrl === '/';

        const items: NavItem[] = [
            {
                title: 'Dashboard',
                href: dashboardHref,
                icon: 'solar:widget-bold-duotone',
                active: isDashboardActive,
            },
        ];

        if (user?.is_superadmin) {
            items.push(
                {
                    title: 'Comercios',
                    href: '/platform/tenants',
                    icon: 'solar:buildings-3-bold-duotone',
                    active: currentUrl.startsWith('/platform/tenants'),
                },
                {
                    title: 'Módulos y Permisos',
                    href: '/platform/modules',
                    icon: 'solar:box-minimalistic-bold-duotone',
                    active: currentUrl.startsWith('/platform/modules'),
                },
            );
        }

        if (tenant) {
            function addModuleGroup(
                parentTitle: string,
                parentIcon: string,
                parentPrefix: string,
                subItems: SubItem[],
                elevateTitleMap?: Record<string, string>
            ) {
                if (subItems.length === 0) return;

                if (subItems.length === 1) {
                    const single = subItems[0];
                    const title = (elevateTitleMap && elevateTitleMap[single.href]) || single.title;
                    items.push({
                        title,
                        href: single.href,
                        icon: single.icon,
                        active: single.active,
                    });
                    return;
                }

                items.push({
                    title: parentTitle,
                    href: subItems[0].href,
                    icon: parentIcon,
                    active: currentUrl.startsWith(parentPrefix) || subItems.some((s) => s.active),
                    subItems,
                });
            }

            // Productos: Acordeón con Catálogo y Buscar en Depósito
            if (modules.includes('productos') && (roles.includes('admin') || user?.is_superadmin || permissions.includes('productos.ver'))) {
                const productosSubItems: SubItem[] = [
                    {
                        title: 'Catálogo de Productos',
                        href: '/tenant/productos',
                        icon: 'solar:box-bold-duotone',
                        active: (currentUrl.startsWith('/tenant/productos') && !currentUrl.startsWith('/tenant/productos/buscar-deposito')) || currentUrl.startsWith('/tenant/categorias'),
                    },
                    {
                        title: 'Buscar en Depósito',
                        href: '/tenant/productos/buscar-deposito',
                        icon: 'solar:box-minimalistic-bold-duotone',
                        active: currentUrl.startsWith('/tenant/productos/buscar-deposito'),
                    },
                ];

                addModuleGroup('Productos', 'solar:box-bold-duotone', '/tenant/productos', productosSubItems, {
                    '/tenant/productos': 'Productos',
                });
            }

            // Stock & Kardex: Acordeón
            if (modules.includes('stock')) {
                const stockSubItems: SubItem[] = [];
                if (isAdmin || permissions.includes('stock_ingreso.ver')) {
                    stockSubItems.push({
                        title: 'Ingresos de Stock',
                        href: '/tenant/stock/ingresos',
                        icon: 'solar:archive-down-minimalistic-bold-duotone',
                        active: currentUrl.startsWith('/tenant/stock/ingresos'),
                    });
                }
                if (isAdmin || permissions.includes('stock_egreso.ver')) {
                    stockSubItems.push({
                        title: 'Egresos de Stock',
                        href: '/tenant/stock/egresos',
                        icon: 'solar:archive-up-minimalistic-bold-duotone',
                        active: currentUrl.startsWith('/tenant/stock/egresos'),
                    });
                }
                if (isAdmin || permissions.includes('stock_historial.ver')) {
                    stockSubItems.push({
                        title: 'Kardex y Auditoría',
                        href: '/tenant/stock/kardex',
                        icon: 'solar:history-bold-duotone',
                        active: currentUrl.startsWith('/tenant/stock/kardex'),
                    });
                }

                addModuleGroup('Stock', 'solar:archive-minimalistic-bold-duotone', '/tenant/stock', stockSubItems);
            }

            // Pedidos: Acordeón con Listado y Repartos
            if (modules.includes('pedidos')) {
                const pedidosSubItems: SubItem[] = [];
                if (isAdmin || permissions.includes('pedidos.ver')) {
                    pedidosSubItems.push({
                        title: 'Listado de Pedidos',
                        href: '/tenant/pedidos',
                        icon: 'solar:bill-list-bold-duotone',
                        active: currentUrl.startsWith('/tenant/pedidos'),
                    });
                }
                if (isAdmin || permissions.includes('repartos.ver')) {
                    pedidosSubItems.push({
                        title: 'Repartos',
                        description: 'Pedidos de entrega',
                        href: '/tenant/repartos',
                        icon: 'solar:delivery-bold-duotone',
                        active: currentUrl.startsWith('/tenant/repartos'),
                    });
                }

                addModuleGroup('Pedidos', 'solar:bill-list-bold-duotone', '/tenant/pedidos', pedidosSubItems, {
                    '/tenant/pedidos': 'Pedidos',
                });
            }

            // Proveedores: Acordeón
            if (modules.includes('proveedores')) {
                const proveedoresSubItems: SubItem[] = [];
                if (isAdmin || permissions.includes('proveedores.ver')) {
                    proveedoresSubItems.push({
                        title: 'Directorio de Proveedores',
                        href: '/tenant/proveedores',
                        icon: 'solar:delivery-bold-duotone',
                        active: currentUrl === '/tenant/proveedores' || currentUrl.startsWith('/tenant/proveedores?'),
                    });
                }
                if (isAdmin || permissions.includes('proveedores.pedidos.ver')) {
                    proveedoresSubItems.push({
                        title: 'Órdenes de Compra',
                        href: '/tenant/proveedores/ordenes',
                        icon: 'solar:clipboard-list-bold-duotone',
                        active: currentUrl.startsWith('/tenant/proveedores/ordenes'),
                    });
                }

                addModuleGroup('Proveedores', 'solar:delivery-bold-duotone', '/tenant/proveedores', proveedoresSubItems, {
                    '/tenant/proveedores': 'Proveedores',
                });
            }

            // Clientes: Acordeón sin Cobranzas
            if (modules.includes('clientes')) {
                const clientesSubItems: SubItem[] = [];
                if (isAdmin || permissions.includes('clientes.ver')) {
                    clientesSubItems.push({
                        title: 'Gestión de Clientes',
                        href: '/tenant/clientes',
                        icon: 'solar:shop-bold-duotone',
                        active: currentUrl === '/tenant/clientes' || currentUrl.startsWith('/tenant/clientes?'),
                    });
                }
                if (isAdmin || permissions.includes('cuentas_corrientes.ver')) {
                    clientesSubItems.push({
                        title: 'Cuentas Corrientes',
                        href: '/tenant/clientes/cuentas-corrientes',
                        icon: 'solar:card-bold-duotone',
                        active: currentUrl.startsWith('/tenant/clientes/cuentas-corrientes'),
                    });
                }

                addModuleGroup('Clientes', 'solar:shop-bold-duotone', '/tenant/clientes', clientesSubItems, {
                    '/tenant/clientes': 'Clientes',
                });
            }

            // Cobranza: Acordeón Independiente de Cobros y Recibos
            if (modules.includes('clientes')) {
                const cobranzasSubItems: SubItem[] = [];
                if (isAdmin || permissions.includes('cobranzas.ver')) {
                    cobranzasSubItems.push({
                        title: 'Panel de Cobranzas',
                        href: '/tenant/cobranzas',
                        icon: 'solar:hand-money-bold-duotone',
                        active: currentUrl === '/tenant/cobranzas' || currentUrl.startsWith('/tenant/cobranzas?'),
                    });
                    cobranzasSubItems.push({
                        title: 'Historial de Recibos',
                        href: '/tenant/cobranzas/recibos',
                        icon: 'solar:bill-check-bold-duotone',
                        active: currentUrl.startsWith('/tenant/cobranzas/recibos'),
                    });
                }

                if (modules.includes('facturacion_arca') && (isAdmin || permissions.includes('facturas.consultar'))) {
                    cobranzasSubItems.push({
                        title: 'Historial de Facturas',
                        href: '/tenant/cobranzas/facturas',
                        icon: 'solar:document-text-bold-duotone',
                        active: currentUrl.startsWith('/tenant/cobranzas/facturas'),
                    });
                }

                addModuleGroup('Cobranza', 'solar:hand-money-bold-duotone', '/tenant/cobranzas', cobranzasSubItems, {
                    '/tenant/cobranzas': 'Cobranza',
                });
            }

            // Administración de Tenant: Enlaces Directos
            if (isAdmin) {
                items.push(
                    {
                        title: 'Personal',
                        href: '/tenant/usuarios',
                        icon: 'solar:users-group-rounded-bold-duotone',
                        active: currentUrl.startsWith('/tenant/usuarios'),
                    },
                    {
                        title: 'Roles',
                        href: '/tenant/roles',
                        icon: 'solar:shield-user-bold-duotone',
                        active: currentUrl.startsWith('/tenant/roles'),
                    },
                );
            }
        }

        return items;
    });
</script>

{#if open}
    <!-- Backdrop oscuro -->
    <div
        transition:fade={{ duration: 200 }}
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm"
        onclick={onClose}
        onkeydown={(e) => e.key === 'Escape' && onClose?.()}
        role="button"
        tabindex="0"
        aria-label="Cerrar navegación"
    ></div>

    <!-- Panel Lateral Deslizante -->
    <aside
        transition:slide={{ axis: 'x', duration: 250 }}
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-background shadow-2xl border-r border-border"
        aria-label="Menú principal móvil"
    >
        <!-- Header del Sidebar Móvil -->
        <div class="flex h-16 items-center justify-between border-b border-border px-4">
            <div class="flex items-center">
                <span class="font-bold text-base tracking-tight">Menú Principal</span>
            </div>
            <button
                type="button"
                onclick={onClose}
                class="flex size-10 min-h-[44px] min-w-[44px] items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                aria-label="Cerrar menú"
            >
                <X class="h-5 w-5" />
            </button>
        </div>

        <!-- Info de Tenant -->
        {#if tenant}
            <div class="border-b border-border bg-muted/30 px-4 py-2.5">
                <span class="text-xs text-muted-foreground font-medium block">Comercio activo:</span>
                <span class="text-sm font-semibold text-foreground truncate block">{tenant.name}</span>
            </div>
        {/if}

        <!-- Navegación con Acordeón de Unicidad -->
        <nav class="flex-1 overflow-y-auto p-3 space-y-1.5">
            {#each navItems as item (item.title)}
                {#if item.subItems && item.subItems.length > 0}
                    <!-- Módulo con Submódulos (Acordeón de Unicidad) -->
                    <div class="space-y-1">
                        <button
                            type="button"
                            onclick={() => toggleModule(item.title)}
                            class="flex w-full min-h-[44px] items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold transition-all {item.active ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'}"
                        >
                            <div class="flex items-center gap-3">
                                <Icon icon={item.icon} class="size-4.5 shrink-0" />
                                <span>{item.title}</span>
                            </div>
                            {#if openModule === item.title}
                                <ChevronDown class="size-4 text-muted-foreground transition-transform" />
                            {:else}
                                <ChevronRight class="size-4 text-muted-foreground transition-transform" />
                            {/if}
                        </button>

                        {#if openModule === item.title}
                            <div class="pl-7 pr-1 space-y-1 border-l-2 border-primary/30 ml-4.5 my-1 animate-in fade-in slide-in-from-top-1 duration-150">
                                {#each item.subItems as sub}
                                    <Link
                                        href={sub.href}
                                        onclick={onClose}
                                        class="flex min-h-[40px] items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition-colors {sub.active ? 'bg-primary/15 text-primary font-semibold' : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'}"
                                    >
                                        <Icon icon={sub.icon} class="size-3.5 shrink-0" />
                                        <span class="truncate">{sub.title}</span>
                                    </Link>
                                {/each}
                            </div>
                        {/if}
                    </div>
                {:else}
                    <!-- Módulo de Enlace Directo Simple -->
                    <Link
                        href={item.href}
                        onclick={onClose}
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors {item.active ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'}"
                    >
                        <Icon icon={item.icon} class="size-4.5 shrink-0" />
                        <span>{item.title}</span>
                    </Link>
                {/if}
            {/each}
        </nav>
    </aside>
{/if}
