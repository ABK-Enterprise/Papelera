<script lang="ts">
    import type { Snippet } from 'svelte';
    import AppNavbarDesktop from '@/components/navbar/desktop/AppNavbarDesktop.svelte';
    import AppHeaderMobile from '@/components/navbar/mobile/AppHeaderMobile.svelte';
    import AppSidebarMobile from '@/components/navbar/mobile/AppSidebarMobile.svelte';
    import { Toaster } from '@/components/ui/sonner';
    import type { BreadcrumbItem } from '@/types';
    import { echo } from '@/lib/echo';
    import { router, page } from '@inertiajs/svelte';

    let {
        breadcrumbs = [],
        children,
    }: {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    } = $props();

    let mobileMenuOpen = $state(false);

    let tenantId = $derived(
        page.props.auth?.tenant?.id ?? page.props.auth?.user?.tenant_id
    );

    $effect(() => {
        if (!tenantId) return;

        const channelName = `tenant.${tenantId}`;
        const moduleRoutes: Record<string, string[]> = {
            stock: ['/tenant/stock'],
            pedidos: ['/tenant/pedidos', '/tenant/repartos'],
            proveedores: ['/tenant/proveedores'],
            clientes: ['/tenant/clientes', '/tenant/cobranzas'],
            productos: ['/tenant/productos', '/tenant/categorias'],
            reportes: ['/tenant/reportes'],
        };

        echo.private(channelName)
            .listen(
                '.TenantModulesUpdated',
                (e: { modules: string[]; detached_modules: string[]; attached_modules: string[] }) => {
                    const detached = e.detached_modules ?? [];
                    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
                    const isCurrentModuleDetached = detached.some((mod) => {
                        const routes = moduleRoutes[mod] ?? [];
                        return routes.some((route) => currentPath.startsWith(route));
                    });

                    if (isCurrentModuleDetached) {
                        router.visit('/auth/access-denied?reason=module_not_assigned', { replace: true });
                    } else {
                        router.reload({ only: ['auth'] });
                    }
                }
            )
            .listen('.TenantPermissionsUpdated', () => {
                router.reload({ only: ['auth'] });
            });

        return () => {
            echo.leave('tenant.' + tenantId);
        };
    });
</script>

<div class="flex min-h-screen flex-col bg-background">
    <!-- Barra de Navegación Desktop -->
    <div class="hidden md:block sticky top-0 z-40">
        <AppNavbarDesktop />
    </div>

    <!-- Barra de Navegación y Drawer Mobile -->
    <div class="block md:hidden sticky top-0 z-40">
        <AppHeaderMobile onToggleMenu={() => (mobileMenuOpen = !mobileMenuOpen)} />
        <AppSidebarMobile open={mobileMenuOpen} onClose={() => (mobileMenuOpen = false)} />
    </div>

    <!-- Contenido Principal -->
    <main class="flex flex-1 flex-col min-w-0">
        {@render children?.()}
    </main>

    <Toaster />
</div>
