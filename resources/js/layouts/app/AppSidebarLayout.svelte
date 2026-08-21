<script lang="ts">
    import type { Snippet } from 'svelte';
    import AppSidebar from '@/components/AppSidebar.svelte';
    import { SidebarProvider } from '@/components/ui/sidebar';
    import { Toaster } from '@/components/ui/sonner';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
        children,
    }: {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    } = $props();
</script>

<SidebarProvider>
    <AppSidebar />
    <main class="flex flex-1 flex-col min-w-0 overflow-y-auto bg-background">
        {@render children?.()}
    </main>
    <Toaster />
</SidebarProvider>
