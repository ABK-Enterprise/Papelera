<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import Icon from '@iconify/svelte';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import UserAvatarDropdown from '@/components/navbar/UserAvatarDropdown.svelte';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';

    interface SubItem {
        title: string;
        description: string;
        href: string;
        icon: string;
        active: boolean;
    }

    interface NavLinkItem {
        title: string;
        href: string;
        icon?: string;
        active: boolean;
        subItems?: SubItem[];
    }

    let auth = $derived(page.props.auth);
    let user = $derived(auth?.user);
    let tenant = $derived(auth?.tenant);
    let roles = $derived(auth?.roles ?? []);
    let permissions = $derived(auth?.permissions ?? []);
    let modules = $derived(auth?.modules ?? []);
    let isAdmin = $derived(auth?.is_admin || roles.includes('admin') || user?.is_superadmin);
    let currentUrl = $derived(page.url);

    let navItems = $derived.by(() => {
        const dashboardHref = user?.is_superadmin ? '/platform/dashboard' : toUrl(dashboard());
        const isDashboardActive = user?.is_superadmin
            ? currentUrl.startsWith('/platform/dashboard') || currentUrl === '/dashboard' || currentUrl === '/'
            : currentUrl === '/dashboard' || currentUrl === '/';

        const items: NavLinkItem[] = [
            {
                title: 'Dashboard',
                href: dashboardHref,
                icon: 'solar:widget-bold-duotone',
                active: isDashboardActive,
            },
        ];

        // Superadmin navigation
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

        // Tenant modular navigation (Zero-Trust con Navigation Menu)
        if (tenant) {
            // Products catalog (Productos module - Enlace Directo Simple)
            if (modules.includes('productos') && (roles.includes('admin') || user?.is_superadmin || permissions.includes('productos.ver'))) {
                items.push({
                    title: 'Productos',
                    href: '/tenant/products',
                    icon: 'solar:box-bold-duotone',
                    active: currentUrl.startsWith('/tenant/products') || currentUrl.startsWith('/tenant/categories'),
                });
            }

            // Stock & Kardex (Stock module)
            if (modules.includes('stock') && (roles.includes('admin') || user?.is_superadmin || permissions.some((p: string) => p.startsWith('stock_')))) {
                items.push({
                    title: 'Stock',
                    href: '/tenant/stock/ingresos',
                    icon: 'solar:archive-minimalistic-bold-duotone',
                    active: currentUrl.startsWith('/tenant/stock'),
                    subItems: [
                        {
                            title: 'Ingresos de Stock',
                            description: 'Registrar entradas y compras a inventario',
                            href: '/tenant/stock/ingresos',
                            icon: 'solar:archive-down-minimalistic-bold-duotone',
                            active: currentUrl.startsWith('/tenant/stock/ingresos'),
                        },
                        {
                            title: 'Egresos de Stock',
                            description: 'Registrar mermas, consumos o salidas',
                            href: '/tenant/stock/egresos',
                            icon: 'solar:archive-up-minimalistic-bold-duotone',
                            active: currentUrl.startsWith('/tenant/stock/egresos'),
                        },
                        {
                            title: 'Kardex y Auditoría',
                            description: 'Historial cronológico de movimientos',
                            href: '/tenant/stock/kardex',
                            icon: 'solar:history-bold-duotone',
                            active: currentUrl.startsWith('/tenant/stock/kardex'),
                        },
                    ],
                });
            }

            // Customer Orders (Pedidos module)
            if (modules.includes('pedidos') && (roles.includes('admin') || user?.is_superadmin || permissions.includes('pedidos.ver'))) {
                items.push({
                    title: 'Pedidos',
                    href: '/tenant/orders',
                    icon: 'solar:bill-list-bold-duotone',
                    active: currentUrl.startsWith('/tenant/orders'),
                });
            }

            // Suppliers (Proveedores module con submenú)
            if (modules.includes('proveedores') && (roles.includes('admin') || user?.is_superadmin || permissions.includes('proveedores.ver') || permissions.includes('proveedores.pedidos.ver'))) {
                items.push({
                    title: 'Proveedores',
                    href: '/tenant/suppliers',
                    icon: 'solar:delivery-bold-duotone',
                    active: currentUrl.startsWith('/tenant/suppliers'),
                    subItems: [
                        {
                            title: 'Directorio de Proveedores',
                            description: 'Fichas comerciales y datos de contacto',
                            href: '/tenant/suppliers',
                            icon: 'solar:delivery-bold-duotone',
                            active: currentUrl === '/tenant/suppliers' || currentUrl.startsWith('/tenant/suppliers?'),
                        },
                        {
                            title: 'Órdenes de Compra',
                            description: 'Emisión y recepción de pedidos a fábricas',
                            href: '/tenant/suppliers/orders',
                            icon: 'solar:clipboard-list-bold-duotone',
                            active: currentUrl.startsWith('/tenant/suppliers/orders'),
                        },
                    ],
                });
            }

            // Customers (Clientes module con Cuentas Corrientes)
            if (modules.includes('clientes') && (roles.includes('admin') || user?.is_superadmin || permissions.includes('clientes.ver'))) {
                items.push({
                    title: 'Clientes',
                    href: '/tenant/customers',
                    icon: 'solar:shop-bold-duotone',
                    active: currentUrl.startsWith('/tenant/customers'),
                    subItems: [
                        {
                            title: 'Gestión de Clientes',
                            description: 'Directorio, condiciones fiscales y saldos',
                            href: '/tenant/customers',
                            icon: 'solar:shop-bold-duotone',
                            active: currentUrl === '/tenant/customers' || currentUrl.startsWith('/tenant/customers?'),
                        },
                        {
                            title: 'Cuentas Corrientes',
                            description: 'Trazabilidad de deuda, cobros y límites',
                            href: '/tenant/customers/accounts',
                            icon: 'solar:card-bold-duotone',
                            active: currentUrl.startsWith('/tenant/customers/accounts'),
                        },
                    ],
                });
            }

            // Tenant administration (Personal y Roles)
            if (isAdmin) {
                items.push(
                    {
                        title: 'Personal',
                        href: '/tenant/users',
                        icon: 'solar:users-group-rounded-bold-duotone',
                        active: currentUrl.startsWith('/tenant/users'),
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

<header class="w-full border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 shadow-[0px_4px_20px_rgba(0,0,0,0.02)]">
    <div class="w-full flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
        <!-- Extremo Izquierdo: Solo Isotipo de la Aplicación -->
        <div class="flex items-center">
            <Link
                href={toUrl(dashboard())}
                class="flex items-center gap-2 rounded-lg p-1.5 transition-transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary"
                aria-label="Ir al Dashboard"
            >
                <AppLogoIcon class="h-8 w-8 text-primary fill-current" />
            </Link>
        </div>

        <!-- Centro: Navigation Menu con soporte On-Hover Dropdown -->
        <nav class="flex items-center justify-center gap-1 sm:gap-2">
            {#each navItems as item (item.href)}
                {#if item.subItems && item.subItems.length > 0}
                    <!-- Item con sub-menú flotante al pasar el mouse (Hover Menu) -->
                    <div class="relative group">
                        <Link
                            href={item.href}
                            class="relative flex items-center gap-1.5 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors rounded-lg {item.active ? 'text-primary bg-primary/10 font-semibold' : 'text-muted-foreground hover:text-foreground hover:bg-muted/60'}"
                        >
                            {#if item.icon}
                                <Icon icon={item.icon} class="size-4 shrink-0" />
                            {/if}
                            <span>{item.title}</span>
                            <ChevronDown class="size-3.5 text-muted-foreground/70 transition-transform duration-200 group-hover:rotate-180" />
                            {#if item.active}
                                <span class="absolute bottom-0 left-3 right-3 h-0.5 bg-primary rounded-full"></span>
                            {/if}
                        </Link>

                        <!-- Panel Flotante del Navigation Menu -->
                        <div class="invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200 ease-out absolute top-full left-1/2 -translate-x-1/2 mt-1.5 w-72 rounded-2xl border border-border/80 bg-popover/98 p-2 shadow-xl backdrop-blur-md z-50">
                            <div class="space-y-1">
                                {#each item.subItems as sub}
                                    <Link
                                        href={sub.href}
                                        class="flex items-start gap-3 rounded-xl p-2.5 transition-colors {sub.active ? 'bg-primary/10 text-primary' : 'hover:bg-muted/60 text-foreground'}"
                                    >
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg {sub.active ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'}">
                                            <Icon icon={sub.icon} class="size-4.5" />
                                        </div>
                                        <div class="space-y-0.5">
                                            <p class="text-xs font-semibold leading-none {sub.active ? 'text-primary' : 'text-foreground'}">
                                                {sub.title}
                                            </p>
                                            <p class="text-[11px] text-muted-foreground leading-snug">
                                                {sub.description}
                                            </p>
                                        </div>
                                    </Link>
                                {/each}
                            </div>
                        </div>
                    </div>
                {:else}
                    <!-- Item simple directo -->
                    <Link
                        href={item.href}
                        class="relative flex items-center gap-1.5 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors rounded-lg {item.active ? 'text-primary bg-primary/10 font-semibold' : 'text-muted-foreground hover:text-foreground hover:bg-muted/60'}"
                    >
                        {#if item.icon}
                            <Icon icon={item.icon} class="size-4 shrink-0" />
                        {/if}
                        <span>{item.title}</span>
                        {#if item.active}
                            <span class="absolute bottom-0 left-3 right-3 h-0.5 bg-primary rounded-full"></span>
                        {/if}
                    </Link>
                {/if}
            {/each}
        </nav>

        <!-- Extremo Derecho: Buscador Rápido y Avatar Interactivo -->
        <div class="flex items-center gap-3">
            <div class="relative hidden lg:flex items-center">
                <Icon icon="solar:magnifer-linear" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <input
                    type="text"
                    placeholder="Buscar..."
                    class="h-9 w-44 rounded-full border border-input bg-muted/30 pl-9 pr-4 text-xs placeholder:text-muted-foreground focus:w-60 focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary transition-all"
                />
            </div>

            <UserAvatarDropdown />
        </div>
    </div>
</header>
