<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import LayoutGrid from 'lucide-svelte/icons/layout-grid';
    import Package from 'lucide-svelte/icons/package';
    import Users from 'lucide-svelte/icons/users';
    import Shield from 'lucide-svelte/icons/shield';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Boxes from 'lucide-svelte/icons/boxes';
    import FileText from 'lucide-svelte/icons/file-text';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Truck from 'lucide-svelte/icons/truck';
    import X from 'lucide-svelte/icons/x';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { Badge } from '@/components/ui/badge';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';

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
    let currentUrl = $derived(page.url);

    let navItems = $derived.by(() => {
        const dashboardHref = user?.is_superadmin ? '/platform/dashboard' : toUrl(dashboard());
        const isDashboardActive = user?.is_superadmin
            ? currentUrl.startsWith('/platform/dashboard') || currentUrl === '/dashboard' || currentUrl === '/'
            : currentUrl === '/dashboard' || currentUrl === '/';

        const items: { title: string; href: string; icon: any; active: boolean }[] = [
            {
                title: 'Dashboard',
                href: dashboardHref,
                icon: LayoutGrid,
                active: isDashboardActive,
            },
        ];

        if (user?.is_superadmin) {
            items.push(
                {
                    title: 'Comercios',
                    href: '/platform/tenants',
                    icon: Building2,
                    active: currentUrl.startsWith('/platform/tenants'),
                },
                {
                    title: 'Módulos y Permisos',
                    href: '/platform/modules',
                    icon: Boxes,
                    active: currentUrl.startsWith('/platform/modules'),
                },
            );
        }

        if (tenant) {
            if (modules.includes('productos') && (user?.is_superadmin || permissions.includes('productos.ver'))) {
                items.push({
                    title: 'Productos',
                    href: '/tenant/products',
                    icon: Package,
                    active: currentUrl.startsWith('/tenant/products') || currentUrl.startsWith('/tenant/categories'),
                });
            }

            if (modules.includes('stock') && (user?.is_superadmin || permissions.some((p: string) => p.startsWith('stock_')))) {
                items.push({
                    title: 'Stock y Kardex',
                    href: '/tenant/stock/ingresos',
                    icon: Boxes,
                    active: currentUrl.startsWith('/tenant/stock'),
                });
            }

            if (modules.includes('pedidos') && (user?.is_superadmin || permissions.includes('pedidos.ver'))) {
                items.push({
                    title: 'Pedidos',
                    href: '/tenant/orders',
                    icon: ShoppingBag,
                    active: currentUrl.startsWith('/tenant/orders'),
                });
            }

            if (modules.includes('proveedores') && (user?.is_superadmin || permissions.includes('proveedores.ver') || permissions.includes('proveedores.pedidos.ver'))) {
                items.push({
                    title: 'Proveedores',
                    href: '/tenant/suppliers',
                    icon: Truck,
                    active: currentUrl.startsWith('/tenant/suppliers'),
                });
            }

            if (roles.includes('admin') || user?.is_superadmin) {
                items.push(
                    {
                        title: 'Personal / Empleados',
                        href: '/tenant/users',
                        icon: Users,
                        active: currentUrl.startsWith('/tenant/users'),
                    },
                    {
                        title: 'Roles y Permisos',
                        href: '/tenant/roles',
                        icon: Shield,
                        active: currentUrl.startsWith('/tenant/roles'),
                    },
                );
            }

            if (modules.includes('facturacion_arca') && (user?.is_superadmin || permissions.some((p: string) => p.startsWith('facturas.')))) {
                items.push({
                    title: 'Facturación ARCA',
                    href: '/playground/arca',
                    icon: FileText,
                    active: currentUrl.startsWith('/playground/arca'),
                });
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
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-background shadow-2xl transition-transform border-r"
        aria-label="Menú principal móvil"
    >
        <!-- Header del Sidebar Móvil -->
        <div class="flex h-16 items-center justify-between border-b px-4">
            <div class="flex items-center gap-2">
                <AppLogoIcon class="h-7 w-7 text-primary fill-current" />
                <span class="font-bold text-base tracking-tight">Menú</span>
            </div>
            <button
                type="button"
                onclick={onClose}
                class="flex h-9 w-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-label="Cerrar menú"
            >
                <X class="h-5 w-5" />
            </button>
        </div>

        <!-- Info de Tenant o Rol -->
        {#if tenant}
            <div class="border-b bg-muted/30 px-4 py-2.5">
                <span class="text-xs text-muted-foreground font-medium block">Comercio:</span>
                <span class="text-sm font-semibold text-foreground truncate block">{tenant.name}</span>
            </div>
        {/if}

        <!-- Navegación -->
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            {#each navItems as item (item.href)}
                <Link
                    href={item.href}
                    onclick={onClose}
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {item.active ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}"
                >
                    <item.icon class="h-5 w-5 shrink-0" />
                    <span>{item.title}</span>
                </Link>
            {/each}
        </nav>
    </aside>
{/if}
