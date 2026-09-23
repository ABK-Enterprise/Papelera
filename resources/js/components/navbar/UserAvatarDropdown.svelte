<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import LogOut from 'lucide-svelte/icons/log-out';
    import Settings from 'lucide-svelte/icons/settings';
    import Shield from 'lucide-svelte/icons/shield';
    import Palette from 'lucide-svelte/icons/palette';
    import Store from 'lucide-svelte/icons/store';
    import Building2 from 'lucide-svelte/icons/building-2';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuGroup,
        DropdownMenuItem,
        DropdownMenuLabel,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
    import { Badge } from '@/components/ui/badge';
    import { getInitials } from '@/lib/initials';
    import { hasModule, hasPermission } from '@/lib/permissions';
    import { toUrl } from '@/lib/utils';
    import { logout } from '@/routes';
    import { edit as editProfile } from '@/routes/profile';
    import { edit as editSecurity } from '@/routes/security';
    import { edit as editAppearance } from '@/routes/appearance';
    import Pencil from 'lucide-svelte/icons/pencil';

    let user = $derived(page.props.auth?.user);
    let tenant = $derived(page.props.auth?.tenant);
    let roles = $derived(page.props.auth?.roles ?? []);
    let isAdmin = $derived(page.props.auth?.is_admin || roles.includes('admin') || user?.is_superadmin);

    let imageFailed = $state(false);
    let avatarSrc = $derived(tenant?.logo_url || user?.avatar || null);
    let hasAvatar = $derived(Boolean(avatarSrc && !imageFailed));

    function handleLogout(e: MouseEvent) {
        e.preventDefault();
        router.flushAll();
        router.post(logout());
    }

    function handleEditLogoRedirect(e: MouseEvent) {
        e.preventDefault();
        if (typeof window !== 'undefined') {
            if (window.location.pathname === '/tenant/configuracion/comercio') {
                setTimeout(() => {
                    window.dispatchEvent(new CustomEvent('open-tenant-logo-modal'));
                }, 300);
            } else {
                router.visit('/tenant/configuracion/comercio?editar_logo=1');
            }
        }
    }
</script>

{#if user}
    <DropdownMenu>
        <DropdownMenuTrigger asChild>
            {#snippet children(props)}
                <button
                    {...props}
                    class="{props.class} relative flex h-9 w-9 items-center justify-center rounded-full ring-2 ring-transparent transition-all hover:ring-primary/40 focus:outline-none focus:ring-primary"
                    aria-label="Menú de usuario"
                >
                    <Avatar class="h-9 w-9 rounded-full border border-border/80 shadow-sm">
                        {#if hasAvatar}
                            <AvatarImage
                                src={avatarSrc!}
                                alt={tenant?.name || user.name}
                                onerror={() => (imageFailed = true)}
                                class="object-cover"
                            />
                        {/if}
                        <AvatarFallback class="rounded-full bg-primary/10 font-medium text-xs text-primary dark:bg-primary/20">
                            {getInitials(tenant?.name || user.name)}
                        </AvatarFallback>
                    </Avatar>
                </button>
            {/snippet}
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-64 sm:w-72 max-w-[calc(100vw-2rem)] p-2 shadow-lg">
            <!-- Header con Datos de Usuario y Comercio -->
            <DropdownMenuLabel class="p-2 font-normal">
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center gap-2.5 min-w-0">
                        {#if tenant && isAdmin}
                            <button
                                type="button"
                                onclick={handleEditLogoRedirect}
                                class="relative h-11 w-11 shrink-0 rounded-full border-2 border-border/80 bg-muted/40 shadow-xs cursor-pointer group hover:scale-105 transition-transform duration-200 focus:outline-none focus:ring-2 focus:ring-primary/50"
                                aria-label="Editar logo del comercio"
                                title="Editar logo del comercio"
                            >
                                <Avatar class="size-full rounded-full overflow-hidden">
                                    {#if hasAvatar}
                                        <AvatarImage
                                            src={avatarSrc!}
                                            alt={tenant?.name || user.name}
                                            class="size-full object-cover"
                                        />
                                    {/if}
                                    <AvatarFallback class="rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                        {getInitials(tenant?.name || user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div
                                    class="absolute -bottom-0.5 -right-0.5 size-4 rounded-full bg-primary text-primary-foreground flex items-center justify-center shadow-xs border-1.5 border-background group-hover:scale-110 transition-transform"
                                >
                                    <Pencil class="size-2.5" />
                                </div>
                            </button>
                        {:else}
                            <Avatar class="h-10 w-10 shrink-0 rounded-full border">
                                {#if hasAvatar}
                                    <AvatarImage src={avatarSrc!} alt={tenant?.name || user.name} class="object-cover" />
                                {/if}
                                <AvatarFallback class="rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                    {getInitials(tenant?.name || user.name)}
                                </AvatarFallback>
                            </Avatar>
                        {/if}
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="truncate text-sm font-semibold text-foreground" title={user.name}>{user.name}</span>
                            <span class="truncate text-xs text-muted-foreground" title={user.email}>{user.email}</span>
                        </div>
                    </div>

                    {#if tenant}
                        <div class="mt-1 flex items-center justify-between gap-2 rounded-md bg-muted/50 px-2.5 py-1 text-xs text-muted-foreground min-w-0">
                            <span class="flex items-center gap-1.5 truncate min-w-0 font-medium" title={tenant.name}>
                                <Building2 class="h-3.5 w-3.5 shrink-0" />
                                <span class="truncate">{tenant.name}</span>
                            </span>
                            <Badge variant="outline" class="text-xs font-medium tracking-wide px-2 py-0.5 shrink-0">Comercio</Badge>
                        </div>
                    {:else if user.is_superadmin}
                        <div class="mt-1 flex items-center justify-between gap-2 rounded-md bg-primary/10 px-2.5 py-1 text-xs text-primary font-medium min-w-0">
                            <span class="truncate min-w-0 max-w-[130px] sm:max-w-none" title="Superadministrador">Superadministrador</span>
                            <Badge variant="default" class="text-xs font-medium tracking-wide px-2 py-0.5 shrink-0">Plataforma</Badge>
                        </div>
                    {/if}
                </div>
            </DropdownMenuLabel>

            <DropdownMenuSeparator />

            <!-- Enlaces de Configuración -->
            <DropdownMenuGroup>
                {#if tenant && isAdmin}
                    <DropdownMenuItem asChild>
                        {#snippet children(props)}
                            <Link
                                class="{props.class} flex items-center gap-2 cursor-pointer py-2 text-sm font-normal"
                                href="/tenant/configuracion/comercio"
                                prefetch
                            >
                                <Store class="h-4 w-4 text-muted-foreground" />
                                <span>Datos del comercio</span>
                            </Link>
                        {/snippet}
                    </DropdownMenuItem>
                    {#if hasModule('stock') && (isAdmin || hasPermission('deposito.gestion'))}
                        <DropdownMenuItem asChild>
                            {#snippet children(props)}
                                <Link
                                    class="{props.class} flex items-center gap-2 cursor-pointer py-2 text-sm font-normal"
                                    href="/tenant/configuracion/depositos"
                                    prefetch
                                >
                                    <Building2 class="h-4 w-4 text-muted-foreground" />
                                    <span>Gestión de depósitos</span>
                                </Link>
                            {/snippet}
                        </DropdownMenuItem>
                    {/if}
                    {#if hasModule('pedidos') && (isAdmin || hasPermission('zonas.gestionar') || hasPermission('zonas.ver'))}
                        <DropdownMenuItem asChild>
                            {#snippet children(props)}
                                <Link
                                    class="{props.class} flex items-center gap-2 cursor-pointer py-2 text-sm font-normal"
                                    href="/tenant/configuracion/zonas"
                                    prefetch
                                >
                                    <MapPin class="h-4 w-4 text-muted-foreground" />
                                    <span>Zonas de envío</span>
                                </Link>
                            {/snippet}
                        </DropdownMenuItem>
                    {/if}
                    <DropdownMenuItem asChild>
                        {#snippet children(props)}
                            <Link
                                class="{props.class} flex items-center gap-2 cursor-pointer py-2 text-sm font-normal"
                                href="/tenant/configuracion/mercadopago"
                                prefetch
                            >
                                <CreditCard class="h-4 w-4 text-muted-foreground" />
                                <span>Mercado Pago</span>
                            </Link>
                        {/snippet}
                    </DropdownMenuItem>
                {/if}


                <DropdownMenuItem asChild>
                    {#snippet children(props)}
                        <Link
                            class="{props.class} flex items-center gap-2 cursor-pointer py-2 text-sm font-normal"
                            href={toUrl(editProfile())}
                            prefetch
                        >
                            <Settings class="h-4 w-4 text-muted-foreground" />
                            <span>Configuración de perfil</span>
                        </Link>
                    {/snippet}
                </DropdownMenuItem>


                <DropdownMenuItem asChild>
                    {#snippet children(props)}
                        <Link
                            class="{props.class} flex items-center gap-2 cursor-pointer py-2 text-sm font-normal"
                            href={toUrl(editSecurity())}
                            prefetch
                        >
                            <Shield class="h-4 w-4 text-muted-foreground" />
                            <span>Seguridad y contraseña</span>
                        </Link>
                    {/snippet}
                </DropdownMenuItem>

                <DropdownMenuItem asChild>
                    {#snippet children(props)}
                        <Link
                            class="{props.class} flex items-center gap-2 cursor-pointer py-2 text-sm font-normal"
                            href={toUrl(editAppearance())}
                            prefetch
                        >
                            <Palette class="h-4 w-4 text-muted-foreground" />
                            <span>Tema y apariencia</span>
                        </Link>
                    {/snippet}
                </DropdownMenuItem>
            </DropdownMenuGroup>

            <DropdownMenuSeparator />

            <!-- Cerrar Sesión -->
            <DropdownMenuItem asChild>
                {#snippet children(props)}
                    <a
                        class="{props.class} flex items-center gap-2 text-destructive hover:bg-destructive/10 hover:text-destructive cursor-pointer py-2"
                        href={logout()}
                        onclick={handleLogout}
                        data-test="logout-button"
                    >
                        <LogOut class="h-4 w-4 shrink-0" />
                        <span>Cerrar sesión</span>
                    </a>
                {/snippet}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
{/if}
