<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Truck from 'lucide-svelte/icons/truck';
    import Users from 'lucide-svelte/icons/users';

    let {
        activeTab = 'directory',
        count,
    }: {
        activeTab?: 'directory' | 'orders';
        count?: number;
    } = $props();

    const tabs = $derived([
        {
            id: 'directory' as const,
            desktopLabel:
                activeTab === 'directory' && typeof count === 'number'
                    ? `Directorio de Proveedores (${count})`
                    : 'Directorio de Proveedores',
            mobileLabel:
                activeTab === 'directory' && typeof count === 'number'
                    ? `Gestión de Proveedores (${count})`
                    : 'Gestión de Proveedores',
            href: '/tenant/proveedores',
            icon: Users,
        },
        {
            id: 'orders' as const,
            desktopLabel:
                activeTab === 'orders' && typeof count === 'number'
                    ? `Órdenes de Compra (${count})`
                    : 'Órdenes de Compra',
            mobileLabel:
                activeTab === 'orders' && typeof count === 'number'
                    ? `Órdenes (${count})`
                    : 'Órdenes',
            href: '/tenant/proveedores/ordenes',
            icon: Truck,
        },
    ]);
</script>

<div class="flex items-center border-b border-border/60 pb-3">
    <div
        class="inline-flex flex-wrap sm:flex-nowrap items-center gap-1 sm:gap-1.5 p-1 rounded-xl bg-muted/70 dark:bg-secondary/45 border border-border/70 shadow-2xs max-w-full"
        role="tablist"
        aria-label="Secciones del módulo de proveedores"
    >
        {#each tabs as tab (tab.id)}
            {@const TabIcon = tab.icon}
            {@const isActive = activeTab === tab.id}
            <Link
                href={tab.href}
                role="tab"
                aria-selected={isActive}
                class="inline-flex items-center justify-center gap-1.5 sm:gap-2 px-2.5 sm:px-3.5 h-auto min-h-[34px] py-[5px] sm:h-[34px] rounded-lg text-[14px] sm:text-sm leading-none transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 whitespace-nowrap {isActive
                    ? 'bg-card dark:bg-primary/20 text-primary dark:text-sky-100 border border-primary/40 dark:border-primary/50 font-semibold shadow-xs hover:bg-card dark:hover:bg-primary/25'
                    : 'bg-transparent border border-transparent text-muted-foreground hover:text-foreground hover:bg-card/70 dark:hover:bg-secondary/70 font-medium'}"
            >
                <TabIcon class="size-3.5 sm:size-4 shrink-0" />
                <span class="sm:hidden">{tab.mobileLabel}</span>
                <span class="hidden sm:inline">{tab.desktopLabel}</span>
            </Link>
        {/each}
    </div>
</div>
