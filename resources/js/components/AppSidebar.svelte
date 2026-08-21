<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import LayoutGrid from 'lucide-svelte/icons/layout-grid';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Boxes from 'lucide-svelte/icons/boxes';
    import Users from 'lucide-svelte/icons/users';
    import Shield from 'lucide-svelte/icons/shield';
    import FileText from 'lucide-svelte/icons/file-text';
    import Package from 'lucide-svelte/icons/package';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    let auth = $derived(page.props.auth);
    let user = $derived(auth?.user);
    let tenant = $derived(auth?.tenant);
    let roles = $derived(auth?.roles ?? []);
    let modules = $derived(auth?.modules ?? []);

    let mainNavItems = $derived.by(() => {
        const items: NavItem[] = [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
        ];

        // Superadmin navigation
        if (user?.is_superadmin) {
            items.push(
                {
                    title: 'Comercios',
                    href: '/platform/tenants',
                    icon: Building2,
                },
                {
                    title: 'Módulos y Permisos',
                    href: '/platform/modules',
                    icon: Boxes,
                },
            );
        }

        // Tenant administration (Admin role)
        if (tenant && (roles.includes('admin') || user?.is_superadmin)) {
            items.push(
                {
                    title: 'Personal / Empleados',
                    href: '/tenant/users',
                    icon: Users,
                },
                {
                    title: 'Roles y Permisos',
                    href: '/tenant/roles',
                    icon: Shield,
                },
            );
        }

        // Active contracted modules
        if (modules.includes('facturacion_arca')) {
            items.push({
                title: 'Facturación ARCA',
                href: '/playground/arca',
                icon: FileText,
            });
        }

        if (modules.includes('stock')) {
            items.push({
                title: 'Stock e Inventario',
                href: '#',
                icon: Package,
            });
        }

        return items;
    });

</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(dashboard())}
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
