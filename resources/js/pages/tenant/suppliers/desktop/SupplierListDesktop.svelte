<script lang="ts">
    import { Button } from '@/components/ui/button';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import Truck from 'lucide-svelte/icons/truck';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Calendar from 'lucide-svelte/icons/calendar';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';

    export interface SupplierItem {
        id: number;
        tenant_id: number;
        name: string;
        contact_number: string | null;
        email: string | null;
        address: string | null;
        is_active: boolean;
        created_at?: string;
        updated_at?: string;
    }

    let {
        suppliers = [],
        onSelectSupplier,
        onOpenCreate,
    }: {
        suppliers: SupplierItem[];
        onSelectSupplier: (supplier: SupplierItem) => void;
        onOpenCreate: () => void;
    } = $props();

    let page = $state(1);
    const perPage = 10;
    let paginatedSuppliers = $derived(suppliers.slice((page - 1) * perPage, page * perPage));

    function formatDate(dateStr?: string) {
        if (!dateStr) return null;
        try {
            const d = new Date(dateStr);
            return d.toLocaleDateString('es-AR', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        } catch {
            return dateStr;
        }
    }
</script>

<div class="flex flex-col gap-3">
    {#each paginatedSuppliers as supplier (supplier.id)}
        <div
            role="button"
            tabindex="0"
            onclick={() => onSelectSupplier(supplier)}
            onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelectSupplier(supplier)}
            class="group flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-2xl border border-border/70 bg-card shadow-xs hover:shadow-md hover:border-border transition-all duration-200 gap-4 cursor-pointer select-none"
        >
            <div class="flex items-start sm:items-center gap-4 min-w-0 flex-1">
                <!-- Avatar / Icono -->
                <div class="h-12 w-12 rounded-xl flex items-center justify-center shrink-0 border transition-transform group-hover:scale-105 bg-primary/10 text-primary border-primary/20">
                    <Truck class="h-6 w-6 stroke-[1.75]" />
                </div>

                <!-- Detalle y Metadatos -->
                <div class="flex flex-col gap-1.5 min-w-0 flex-1">
                    <!-- Fila 1: Nombre Destacado + Badge de Estado -->
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h3 class="font-bold text-base text-foreground group-hover:text-primary transition-colors truncate">
                            {supplier.name}
                        </h3>

                        {#if supplier.is_active}
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Activo
                            </span>
                        {:else}
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-muted text-muted-foreground border border-border">
                                <span class="h-1.5 w-1.5 rounded-full bg-muted-foreground"></span>
                                Inactivo
                            </span>
                        {/if}
                    </div>

                    <!-- Fila 2: Badges de Contacto y Correo -->
                    <div class="flex items-center gap-2.5 flex-wrap text-xs text-muted-foreground">
                        {#if supplier.contact_number}
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-muted/60 px-2.5 py-0.5 text-xs font-medium text-foreground/90 border border-border/40 font-mono tabular-nums">
                                <Phone class="h-3 w-3 text-muted-foreground" />
                                {supplier.contact_number}
                            </span>
                        {/if}

                        {#if supplier.email}
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-muted/60 px-2.5 py-0.5 text-xs font-medium text-foreground/90 border border-border/40 font-mono">
                                <Mail class="h-3 w-3 text-muted-foreground" />
                                {supplier.email}
                            </span>
                        {/if}

                        {#if !supplier.contact_number && !supplier.email}
                            <span class="text-xs text-muted-foreground italic">
                                Sin datos de contacto registrados
                            </span>
                        {/if}

                        {#if supplier.address}
                            <div class="flex items-center gap-1 truncate max-w-md">
                                <MapPin class="h-3 w-3 text-muted-foreground/70" />
                                <span class="truncate">{supplier.address}</span>
                            </div>
                        {/if}

                        {#if supplier.created_at}
                            <div class="flex items-center gap-1 shrink-0 ml-auto sm:ml-0 tabular-nums">
                                <Calendar class="h-3 w-3 text-muted-foreground/70" />
                                <span>Alta: {formatDate(supplier.created_at)}</span>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Lado Derecho: Acción Rápida con Touch Target >= 44px -->
            <div class="flex items-center gap-2 shrink-0">
                <Button
                    variant="ghost"
                    size="sm"
                    class="min-h-[44px] px-3.5 text-xs font-medium gap-1.5 rounded-xl text-muted-foreground group-hover:text-primary group-hover:bg-primary/10 transition-colors"
                >
                    <span>Gestionar</span>
                    <ChevronRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                </Button>
            </div>
        </div>
    {/each}

    {#if suppliers.length === 0}
        <EmptyState
            icon={Truck}
            title="No se encontraron proveedores"
            description="No hay proveedores que coincidan con la búsqueda o todavía no has añadido ninguno a tu comercio."
            actionLabel="Añadir Primer Proveedor"
            onaction={onOpenCreate}
        />
    {/if}

    {#if suppliers.length > perPage}
        <AppPagination count={suppliers.length} {perPage} bind:page />
    {/if}
</div>
