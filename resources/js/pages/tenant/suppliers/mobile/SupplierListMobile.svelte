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
    const perPage = 6;
    let paginatedSuppliers = $derived(suppliers.slice((page - 1) * perPage, page * perPage));

    function formatDate(dateStr?: string) {
        if (!dateStr) return null;
        try {
            const d = new Date(dateStr);
            return new Intl.DateTimeFormat('es-AR', {
                dateStyle: 'short',
            }).format(d);
        } catch {
            return dateStr;
        }
    }
</script>

<div class="flex flex-col gap-3">
    {#each paginatedSuppliers as supplier (supplier.id)}
        <div
            class="flex flex-col min-h-[56px] gap-2.5 rounded-2xl border border-border/70 bg-card p-4 shadow-2xs active:bg-muted/40 transition-colors cursor-pointer select-none"
            onclick={() => onSelectSupplier(supplier)}
            onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelectSupplier(supplier)}
            role="button"
            tabindex="0"
            aria-label={`Gestionar proveedor ${supplier.name}`}
        >
            <!-- Cabecera Mobile: Nombre + Estado + Icono Chevron -->
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Truck class="h-5 w-5 stroke-[1.75]" />
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-semibold text-sm text-foreground truncate">
                            {supplier.name}
                        </h4>
                        {#if supplier.created_at}
                            <span class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5 tabular-nums">
                                <Calendar class="h-3 w-3" />
                                {formatDate(supplier.created_at)}
                            </span>
                        {/if}
                    </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                    {#if supplier.is_active}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium tracking-wide bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                            Activo
                        </span>
                    {:else}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium tracking-wide bg-muted text-muted-foreground">
                            Inactivo
                        </span>
                    {/if}
                    <ChevronRight class="h-4 w-4 text-muted-foreground" />
                </div>
            </div>

            <!-- Datos de contacto & Email -->
            {#if supplier.contact_number || supplier.email}
                <div class="flex items-center gap-2 flex-wrap pt-2 border-t border-border/40 text-xs">
                    {#if supplier.contact_number}
                        <span class="inline-flex items-center gap-1 font-mono bg-muted/60 px-2 py-0.5 rounded text-foreground tabular-nums text-xs">
                            <Phone class="h-3 w-3 text-muted-foreground" />
                            {supplier.contact_number}
                        </span>
                    {/if}
                    {#if supplier.email}
                        <span class="inline-flex items-center gap-1 font-mono bg-muted/60 px-2 py-0.5 rounded text-foreground truncate max-w-[200px] text-xs">
                            <Mail class="h-3 w-3 text-muted-foreground" />
                            {supplier.email}
                        </span>
                    {/if}
                </div>
            {/if}

            <!-- Dirección -->
            {#if supplier.address}
                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <MapPin class="h-3.5 w-3.5 shrink-0 text-muted-foreground/70" />
                    <span class="truncate">{supplier.address}</span>
                </div>
            {/if}
        </div>
    {/each}

    {#if suppliers.length === 0}
        <EmptyState
            icon={Truck}
            title="No se encontraron proveedores"
            description="No hay proveedores que coincidan con la búsqueda o filtros."
            actionLabel="Añadir proveedor"
            onaction={onOpenCreate}
        />
    {/if}

    {#if suppliers.length > perPage}
        <AppPagination count={suppliers.length} {perPage} bind:page />
    {/if}
</div>
