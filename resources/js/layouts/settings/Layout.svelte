<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import { Button } from '@/components/ui/button';
    import { Separator } from '@/components/ui/separator';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import { edit as editAppearance } from '@/routes/appearance';
    import { edit as editProfile } from '@/routes/profile';
    import { edit as editSecurity } from '@/routes/security';
    import Store from 'lucide-svelte/icons/store';
    import Building2 from 'lucide-svelte/icons/building-2';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import User from 'lucide-svelte/icons/user';
    import Shield from 'lucide-svelte/icons/shield';
    import Palette from 'lucide-svelte/icons/palette';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    let user = $derived(page.props.auth?.user);
    let tenant = $derived(page.props.auth?.tenant);
    let roles = $derived(page.props.auth?.roles ?? []);
    let isAdmin = $derived(Boolean(page.props.auth?.is_admin || roles.includes('admin') || user?.is_superadmin));

    const commerceNavItems = [
        {
            title: 'Datos del Comercio',
            href: '/tenant/configuracion/comercio',
            icon: Store,
        },
        {
            title: 'Depósitos',
            href: '/tenant/configuracion/depositos',
            icon: Building2,
        },
        {
            title: 'Zonas de Envío',
            href: '/tenant/configuracion/zonas',
            icon: MapPin,
        },
        {
            title: 'Mercado Pago',
            href: '/tenant/configuracion/mercadopago',
            icon: CreditCard,
        },
    ];

    const accountNavItems = [
        {
            title: 'Perfil',
            href: editProfile(),
            icon: User,
        },
        {
            title: 'Seguridad',
            href: editSecurity(),
            icon: Shield,
        },
        {
            title: 'Apariencia',
            href: editAppearance(),
            icon: Palette,
        },
    ];

    const url = currentUrlState();
</script>

<div class="px-4 py-6 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
    <!-- Encabezado Unificado del Módulo -->
    <div class="mb-6 flex flex-col gap-1">
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
            Configuración
        </h1>
    </div>

    <div class="flex flex-col lg:flex-row lg:items-start gap-6 lg:gap-8">
        <!-- Sidebar Interno con Separador Vertical y Margen en Desktop -->
        <aside class="w-full lg:w-64 shrink-0 lg:border-r border-border/80 lg:pr-6 lg:mr-2">
            <nav class="flex flex-col space-y-4" aria-label="Navegación de Configuración">
                <!-- Grupo Comercio (Exclusivo Administradores) -->
                {#if tenant && isAdmin}
                    <div class="space-y-1">
                        <div class="px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            Comercio
                        </div>
                        <div class="flex flex-col space-y-1">
                            {#each commerceNavItems as item (toUrl(item.href))}
                                {@const isActive = url.isCurrentUrl(item.href, url.currentUrl)}
                                {@const Icon = item.icon}
                                <Button
                                    variant="ghost"
                                    class="w-full justify-start min-h-[40px] px-3 rounded-xl gap-2.5 text-sm transition-colors {isActive
                                        ? 'bg-primary/10 text-primary font-semibold hover:bg-primary/15'
                                        : 'text-muted-foreground hover:text-foreground hover:bg-muted/60'}"
                                    asChild
                                >
                                    {#snippet children(props)}
                                        <Link href={toUrl(item.href)} class={props.class} prefetch>
                                            <Icon class="size-4 shrink-0 {isActive ? 'text-primary' : 'text-muted-foreground'}" />
                                            <span>{item.title}</span>
                                        </Link>
                                    {/snippet}
                                </Button>
                            {/each}
                        </div>
                    </div>
                {/if}

                <!-- Grupo Mi Cuenta (Todos los usuarios) -->
                <div class="space-y-1">
                    <div class="px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        Mi Cuenta
                    </div>
                    <div class="flex flex-col space-y-1">
                        {#each accountNavItems as item (toUrl(item.href))}
                            {@const isActive = url.isCurrentUrl(item.href, url.currentUrl)}
                            {@const Icon = item.icon}
                            <Button
                                variant="ghost"
                                class="w-full justify-start min-h-[40px] px-3 rounded-xl gap-2.5 text-sm transition-colors {isActive
                                    ? 'bg-primary/10 text-primary font-semibold hover:bg-primary/15'
                                    : 'text-muted-foreground hover:text-foreground hover:bg-muted/60'}"
                                asChild
                            >
                                {#snippet children(props)}
                                    <Link href={toUrl(item.href)} class={props.class} prefetch>
                                        <Icon class="size-4 shrink-0 {isActive ? 'text-primary' : 'text-muted-foreground'}" />
                                        <span>{item.title}</span>
                                    </Link>
                                {/snippet}
                            </Button>
                        {/each}
                    </div>
                </div>
            </nav>
        </aside>

        <Separator class="my-2 lg:hidden" />

        <!-- Columna de Contenido Fluido -->
        <div class="flex-1 min-w-0 w-full max-w-7xl">
            <section class="w-full">
                {@render children?.()}
            </section>
        </div>
    </div>
</div>
