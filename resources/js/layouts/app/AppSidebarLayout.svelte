<script lang="ts">
    import type { Snippet } from 'svelte';
    import AppNavbarDesktop from '@/components/navbar/desktop/AppNavbarDesktop.svelte';
    import AppHeaderMobile from '@/components/navbar/mobile/AppHeaderMobile.svelte';
    import AppSidebarMobile from '@/components/navbar/mobile/AppSidebarMobile.svelte';
    import { Toaster } from '@/components/ui/sonner';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
        children,
    }: {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    } = $props();

    let mobileMenuOpen = $state(false);
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
