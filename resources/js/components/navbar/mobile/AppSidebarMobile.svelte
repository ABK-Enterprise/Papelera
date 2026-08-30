<script lang="ts">
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import { Link, page } from '@inertiajs/svelte';
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
            if (currentUrl.startsWith('/tenant/stock')) {
                openModule = 'Stock';
            } else if (currentUrl.startsWith('/tenant/suppliers')) {
                openModule = 'Proveedores';
            } else if (currentUrl.startsWith('/tenant/customers')) {
                openModule = 'Clientes';
            } else {
                openModule = null;
            }
        }
    });

    function toggleModule(moduleTitle: string) {
        openModule = openModule === moduleTitle ? null : moduleTitle;
    }

    interface SubItem {
        title: string;
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
            // Productos: Enlace Directo Simple (Sin submódulos)
            if (modules.includes('productos') && (isAdmin || permissions.includes('productos.ver'))) {
                items.push({
                    title: 'Productos',
                    href: '/tenant/products',
                    icon: 'solar:box-bold-duotone',
                    active: currentUrl.startsWith('/tenant/products') || currentUrl.startsWith('/tenant/categories'),
                });
            }

            // Stock & Kardex: Acordeón
            if (modules.includes('stock') && (isAdmin || permissions.some((p: string) => p.startsWith('stock_')))) {
                items.push({
                    title: 'Stock',
                    href: '/tenant/stock/ingresos',
                    icon: 'solar:archive-minimalistic-bold-duotone',
                    active: currentUrl.startsWith('/tenant/stock'),
                    subItems: [
                        {
                            title: 'Ingresos de Stock',
                            href: '/tenant/stock/ingresos',
                            icon: 'solar:archive-down-minimalistic-bold-duotone',
                            active: currentUrl.startsWith('/tenant/stock/ingresos'),
                        },
                        {
                            title: 'Egresos de Stock',
                            href: '/tenant/stock/egresos',
                            icon: 'solar:archive-up-minimalistic-bold-duotone',
                            active: currentUrl.startsWith('/tenant/stock/egresos'),
                        },
                        {
                            title: 'Kardex y Auditoría',
                            href: '/tenant/stock/kardex',
                            icon: 'solar:history-bold-duotone',
                            active: currentUrl.startsWith('/tenant/stock/kardex'),
                        },
                    ],
                });
            }

            // Pedidos: Enlace Directo Simple
            if (modules.includes('pedidos') && (isAdmin || permissions.includes('pedidos.ver'))) {
                items.push({
                    title: 'Pedidos',
                    href: '/tenant/orders',
                    icon: 'solar:bill-list-bold-duotone',
                    active: currentUrl.startsWith('/tenant/orders'),
                });
            }

            // Proveedores: Acordeón
            if (modules.includes('proveedores') && (isAdmin || permissions.includes('proveedores.ver') || permissions.includes('proveedores.pedidos.ver'))) {
                items.push({
                    title: 'Proveedores',
                    href: '/tenant/suppliers',
                    icon: 'solar:delivery-bold-duotone',
                    active: currentUrl.startsWith('/tenant/suppliers'),
                    subItems: [
                        {
                            title: 'Directorio de Proveedores',
                            href: '/tenant/suppliers',
                            icon: 'solar:delivery-bold-duotone',
                            active: currentUrl === '/tenant/suppliers' || currentUrl.startsWith('/tenant/suppliers?'),
                        },
                        {
                            title: 'Órdenes de Compra',
                            href: '/tenant/suppliers/orders',
                            icon: 'solar:clipboard-list-bold-duotone',
                            active: currentUrl.startsWith('/tenant/suppliers/orders'),
                        },
                    ],
                });
            }

            // Clientes: Acordeón
            if (modules.includes('clientes') && (isAdmin || permissions.includes('clientes.ver'))) {
                items.push({
                    title: 'Clientes',
                    href: '/tenant/customers',
                    icon: 'solar:shop-bold-duotone',
                    active: currentUrl.startsWith('/tenant/customers'),
                    subItems: [
                        {
                            title: 'Gestión de Clientes',
                            href: '/tenant/customers',
                            icon: 'solar:shop-bold-duotone',
                            active: currentUrl === '/tenant/customers' || currentUrl.startsWith('/tenant/customers?'),
                        },
                        {
                            title: 'Cuentas Corrientes',
                            href: '/tenant/customers/accounts',
                            icon: 'solar:card-bold-duotone',
                            active: currentUrl.startsWith('/tenant/customers/accounts'),
                        },
                    ],
                });
            }

            // Administración de Tenant: Enlaces Directos
            if (isAdmin) {
                items.push(
                    {
                        title: 'Personal / Empleados',
                        href: '/tenant/users',
                        icon: 'solar:users-group-rounded-bold-duotone',
                        active: currentUrl.startsWith('/tenant/users'),
                    },
                    {
                        title: 'Roles y Permisos',
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
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm transition-opacity"
        onclick={onClose}
        onkeydown={(e) => e.key === 'Escape' && onClose?.()}
        role="button"
        tabindex="0"
        aria-label="Cerrar navegación"
    ></div>

    <!-- Panel Lateral Deslizante -->
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-background shadow-2xl transition-transform border-r border-border"
        aria-label="Menú principal móvil"
    >
        <!-- Header del Sidebar Móvil -->
        <div class="flex h-16 items-center justify-between border-b border-border px-4">
            <div class="flex items-center gap-2">
                <AppLogoIcon class="h-7 w-7 text-primary fill-current" />
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
