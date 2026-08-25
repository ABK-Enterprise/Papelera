<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import LayoutDashboard from 'lucide-svelte/icons/layout-dashboard';
    import Store from 'lucide-svelte/icons/store';
    import Puzzle from 'lucide-svelte/icons/puzzle';
    import Users from 'lucide-svelte/icons/users';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
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
                icon: LayoutDashboard,
            },
        ];

        // Superadmin navigation
        if (user?.is_superadmin) {
            items.push(
                {
                    title: 'Comercios',
                    href: '/platform/tenants',
                    icon: Store,
                },
                {
                    title: 'Módulos y Permisos',
                    href: '/platform/modules',
                    icon: Puzzle,
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
                    icon: ShieldCheck,
                },
            );
        }

        if (modules.includes('inventario_stock') || modules.includes('stock')) {
            items.push({
                title: 'Stock e Inventario',
                href: '/tenant/products',
                icon: Package,
            });
        }

        return items;
    });

</script>

<Sidebar collapsible="none" variant="sidebar" class="w-64 border-r border-sidebar-border bg-sidebar shrink-0 min-h-screen">
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
