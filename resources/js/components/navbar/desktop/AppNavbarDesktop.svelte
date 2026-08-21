<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import LayoutGrid from 'lucide-svelte/icons/layout-grid';
    import Package from 'lucide-svelte/icons/package';
    import Users from 'lucide-svelte/icons/users';
    import Shield from 'lucide-svelte/icons/shield';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Boxes from 'lucide-svelte/icons/boxes';
    import FileText from 'lucide-svelte/icons/file-text';
    import Search from 'lucide-svelte/icons/search';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import UserAvatarDropdown from '@/components/navbar/UserAvatarDropdown.svelte';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';

    interface NavLinkItem {
        title: string;
        href: string;
        icon?: any;
        active?: boolean;
    }

    let auth = $derived(page.props.auth);
    let user = $derived(auth?.user);
    let tenant = $derived(auth?.tenant);
    let roles = $derived(auth?.roles ?? []);
    let modules = $derived(auth?.modules ?? []);
    let currentUrl = $derived(page.url);

    let navItems = $derived.by(() => {
        const items: NavLinkItem[] = [
            {
                title: 'Dashboard',
                href: toUrl(dashboard()),
                icon: LayoutGrid,
                active: currentUrl === '/dashboard' || currentUrl === '/',
            },
        ];

        // Superadmin navigation
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

        // Tenant modular navigation
        if (tenant) {
            // Products catalog (Stock module)
            if (modules.includes('stock') || user?.is_superadmin || roles.includes('admin')) {
                items.push({
                    title: 'Productos',
                    href: '/tenant/products',
                    icon: Package,
                    active: currentUrl.startsWith('/tenant/products') || currentUrl.startsWith('/tenant/categories'),
                });
            }

            // Tenant administration
            if (roles.includes('admin') || user?.is_superadmin) {
                items.push(
                    {
                        title: 'Personal',
                        href: '/tenant/users',
                        icon: Users,
                        active: currentUrl.startsWith('/tenant/users'),
                    },
                    {
                        title: 'Roles',
                        href: '/tenant/roles',
                        icon: Shield,
                        active: currentUrl.startsWith('/tenant/roles'),
                    },
                );
            }

            // Facturación ARCA
            if (modules.includes('facturacion_arca')) {
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

<header class="w-full border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 shadow-[0px_4px_20px_rgba(0,0,0,0.02)]">
    <div class="max-w-7xl mx-auto flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
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

        <!-- Centro: Enlaces de Navegación Centrados y sin Agrupar -->
        <nav class="flex items-center justify-center gap-1 sm:gap-2">
            {#each navItems as item (item.href)}
                <Link
                    href={item.href}
                    class="relative flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium transition-colors rounded-lg {item.active ? 'text-primary bg-primary/10 font-semibold' : 'text-muted-foreground hover:text-foreground hover:bg-muted/60'}"
                >
                    {#if item.icon}
                        <item.icon class="h-4 w-4 shrink-0" />
                    {/if}
                    <span>{item.title}</span>
                    {#if item.active}
                        <span class="absolute bottom-0 left-3 right-3 h-0.5 bg-primary rounded-full"></span>
                    {/if}
                </Link>
            {/each}
        </nav>

        <!-- Extremo Derecho: Buscador Rápido y Avatar Interactivo -->
        <div class="flex items-center gap-3">
            <div class="relative hidden lg:flex items-center">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
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
