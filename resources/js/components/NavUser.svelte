<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import LogOut from 'lucide-svelte/icons/log-out';
    import Settings from 'lucide-svelte/icons/settings';
    import {
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import UserInfo from '@/components/UserInfo.svelte';
    import { toUrl } from '@/lib/utils';
    import { logout } from '@/routes';
    import { edit } from '@/routes/profile';

    const user = $derived(page.props.auth.user);

    function handleLogout(event: MouseEvent) {
        event.preventDefault();
        router.flushAll();
        router.post(logout());
    }
</script>

{#if user}
    <div class="flex flex-col gap-2 p-1">
        <!-- Direct Nav Links in Spanish -->
        <SidebarMenu class="gap-1">
            <SidebarMenuItem>
                <SidebarMenuButton size="default" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(edit())}
                            prefetch
                            class="{props.class} font-medium"
                            data-test="settings-button"
                        >
                            <Settings class="size-4 shrink-0 text-muted-foreground" />
                            <span>Configuración</span>
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton size="default" asChild>
                    {#snippet children(props)}
                        <a
                            {...props}
                            href={logout()}
                            onclick={handleLogout}
                            class="{props.class} font-medium text-destructive hover:bg-destructive/10 hover:text-destructive"
                            data-test="logout-button"
                        >
                            <LogOut class="size-4 shrink-0" />
                            <span>Cerrar sesión</span>
                        </a>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>

        <div class="my-1 h-px w-full bg-sidebar-border/60"></div>

        <!-- User Info Card at the bottom -->
        <div class="flex items-center gap-3 rounded-lg border border-sidebar-border/60 bg-sidebar-accent/30 p-2.5">
            <UserInfo {user} showEmail={true} />
        </div>
    </div>
{/if}
