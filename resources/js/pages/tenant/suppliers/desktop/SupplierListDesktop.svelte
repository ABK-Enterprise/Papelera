<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import AppPagination, { type PaginationMeta } from '@/components/common/AppPagination.svelte';
    import Truck from 'lucide-svelte/icons/truck';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Calendar from 'lucide-svelte/icons/calendar';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import Plus from 'lucide-svelte/icons/plus';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import { hasPermission } from '@/lib/permissions';

    export interface SupplierItem {
        id: number;
        tenant_id: number;
        name: string;
        tax_id?: string | null;
        contact_number: string | null;
        email: string | null;
        address: string | null;
        is_active: boolean;
        created_at?: string;
        updated_at?: string;
    }

    let {
        suppliers = [],
        pagination = null,
        onSelectSupplier,
        onOpenCreate,
    }: {
        suppliers: SupplierItem[];
        pagination?: PaginationMeta | null;
        onSelectSupplier: (supplier: SupplierItem) => void;
        onOpenCreate: () => void;
    } = $props();

    function formatDate(dateStr?: string) {
        if (!dateStr) return null;
        try {
            const d = new Date(dateStr);
            return new Intl.DateTimeFormat('es-AR', {
                dateStyle: 'medium',
            }).format(d);
        } catch {
            return dateStr;
        }
    }
</script>

<div class="flex flex-col gap-3.5">
    {#each suppliers as supplier (supplier.id)}
        <div
            class="bento-card group flex items-center justify-between gap-6 rounded-xl border border-border/60 bg-card p-5 shadow-[0px_10px_30px_rgba(0,0,0,0.02)] transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-accent/25 hover:border-primary/40 cursor-pointer"

            onclick={() => onSelectSupplier(supplier)}
            onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelectSupplier(supplier)}
            role="button"
            tabindex="0"
            aria-label={`Gestionar proveedor ${supplier.name}`}
        >
            <!-- Lado Izquierdo: Icono + Información Principal (Wide Hierarchy) -->
            <div class="flex items-center gap-4.5 min-w-0 flex-1">
                <!-- Icon Avatar Badge -->
                <IconContainer
                    size="xl"
                    variant="primary"
                    icon="solar:delivery-bold-duotone"
                    class="transition-transform group-hover:scale-105"
                />

                <!-- Detalle y Metadatos -->
                <div class="flex flex-col gap-1.5 min-w-0 flex-1">
                    <!-- Fila 1: Nombre Destacado + Badge de Estado -->
                    <div class="flex items-center gap-3">
                        <h3 class="font-medium text-sm text-foreground group-hover:text-primary transition-colors truncate">
                            {supplier.name}
                        </h3>

                        {#if supplier.is_active}
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-500/30">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Activo
                            </span>
                        {:else}
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-muted/60 dark:bg-secondary/40 text-muted-foreground border border-border/80 dark:border-border/90">
                                <span class="h-1.5 w-1.5 rounded-full bg-muted-foreground"></span>
                                Inactivo
                            </span>
                        {/if}
                    </div>

                    <!-- Fila 2: Badges de Contacto y Correo -->
                    <div class="flex items-center gap-2.5 flex-wrap">
                        {#if supplier.tax_id}
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-muted/40 dark:bg-secondary/35 px-2.5 py-1 text-xs tabular-nums text-foreground/90 border border-border/80 dark:border-border/90 font-medium">
                                CUIT: {supplier.tax_id}
                            </span>
                        {/if}

                        {#if supplier.contact_number}
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-muted/40 dark:bg-secondary/35 px-2.5 py-1 text-xs tabular-nums text-foreground/90 border border-border/80 dark:border-border/90">
                                <Phone class="size-3.5 text-muted-foreground" />
                                {supplier.contact_number}
                            </span>
                        {/if}

                        {#if supplier.email}
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-muted/40 dark:bg-secondary/35 px-2.5 py-1 text-xs font-medium text-foreground/90 border border-border/80 dark:border-border/90">
                                <Mail class="size-3.5 text-muted-foreground" />
                                {supplier.email}
                            </span>
                        {/if}

                        {#if !supplier.contact_number && !supplier.email && !supplier.tax_id}
                            <span class="text-xs text-muted-foreground italic">
                                Sin datos de contacto registrados
                            </span>
                        {/if}
                    </div>

                    <!-- Fila 3: Dirección y Fecha de Alta -->
                    <div class="flex items-center gap-4 text-xs text-muted-foreground mt-0.5">
                        {#if supplier.address}
                            <div class="flex items-center gap-1 truncate max-w-md">
                                <MapPin class="size-3.5 shrink-0 text-muted-foreground" />
                                <span class="truncate">{supplier.address}</span>
                            </div>
                        {/if}

                        {#if supplier.created_at}
                            <div class="flex items-center gap-1 shrink-0">
                                <Calendar class="size-3.5 text-muted-foreground" />
                                <span>Alta: {formatDate(supplier.created_at)}</span>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Lado Derecho: Acción Rápida / Indicador -->
            <div class="flex items-center gap-2 shrink-0">
                <Button
                    variant="tertiary"
                    size="sm"
                    class="min-h-[38px] px-3.5 text-sm gap-1.5 font-medium rounded-xl"
                >
                    <span>Gestionar</span>
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>
    {/each}

    {#if suppliers.length === 0}
        <div class="rounded-xl border border-dashed border-border/80 dark:border-border/90 p-12 text-center bg-muted/30 dark:bg-secondary/20">
            <Truck class="mx-auto h-12 w-12 text-muted-foreground/80 mb-3" />
            <h3 class="font-semibold text-lg text-foreground">No se encontraron proveedores</h3>
            <p class="text-sm text-muted-foreground mt-1 max-w-sm mx-auto">
                No hay proveedores que coincidan con la búsqueda o aún no has añadido ninguno a tu comercio.
            </p>
            {#if hasPermission('proveedores.crear')}
                <Button class="mt-4 gap-2 min-h-[38px] text-sm font-medium" onclick={onOpenCreate}>
                    <Plus class="h-4 w-4" />
                    Añadir Primer Proveedor
                </Button>
            {/if}
        </div>
    {/if}

    <AppPagination {pagination} />
</div>
