<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import LayoutGrid from 'lucide-svelte/icons/layout-grid';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Boxes from 'lucide-svelte/icons/boxes';
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

    let user = $derived(page.props.auth?.user);

    let mainNavItems = $derived.by(() => {
        const items: NavItem[] = [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
        ];

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
