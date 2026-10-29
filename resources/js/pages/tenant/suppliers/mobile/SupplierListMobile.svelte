<script lang="ts">
    import { Button } from '@/components/ui/button';
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
                dateStyle: 'short',
            }).format(d);
        } catch {
            return dateStr;
        }
    }
</script>

<div class="flex flex-col gap-3">
    {#each suppliers as supplier (supplier.id)}
        <div
            class="flex flex-col gap-2.5 rounded-xl border border-border/70 bg-card p-4 shadow-sm transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-accent/25 cursor-pointer"

            onclick={() => onSelectSupplier(supplier)}
            onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelectSupplier(supplier)}
            role="button"
            tabindex="0"
            aria-label={`Gestionar proveedor ${supplier.name}`}
        >
            <!-- Cabecera Mobile: Nombre + Estado + Icono Chevron -->
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0">
                    <IconContainer size="sm" rounded="lg" variant="primary" icon="solar:delivery-bold-duotone" />
                    <div class="min-w-0">
                        <h4 class="font-bold text-sm text-foreground truncate">
                            {supplier.name}
                        </h4>
                        {#if supplier.created_at}
                            <span class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                                <Calendar class="size-3" />
                                {formatDate(supplier.created_at)}
                            </span>
                        {/if}
                    </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                    {#if supplier.is_active}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                            Activo
                        </span>
                    {:else}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-muted text-muted-foreground">
                            Inactivo
                        </span>
                    {/if}
                    <ChevronRight class="size-4 text-muted-foreground" />
                </div>
            </div>

            <!-- Datos de contacto & Email -->
            {#if supplier.tax_id || supplier.contact_number || supplier.email}
                <div class="flex items-center gap-2 flex-wrap pt-1 border-t border-border/40">
                    {#if supplier.tax_id}
                        <span class="inline-flex items-center gap-1 text-xs tabular-nums bg-muted/60 px-2 py-0.5 rounded text-foreground font-medium">
                            CUIT: {supplier.tax_id}
                        </span>
                    {/if}
                    {#if supplier.contact_number}
                        <span class="inline-flex items-center gap-1 text-xs tabular-nums bg-muted/60 px-2 py-0.5 rounded text-foreground">
                            <Phone class="size-3 text-muted-foreground" />
                            {supplier.contact_number}
                        </span>
                    {/if}
                    {#if supplier.email}
                        <span class="inline-flex items-center gap-1 text-xs bg-muted/60 px-2 py-0.5 rounded text-foreground truncate max-w-[200px]">
                            <Mail class="size-3 text-muted-foreground" />
                            {supplier.email}
                        </span>
                    {/if}
                </div>
            {/if}

            <!-- Dirección -->
            {#if supplier.address}
                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <MapPin class="size-3.5 shrink-0 text-muted-foreground/70" />
                    <span class="truncate">{supplier.address}</span>
                </div>
            {/if}
        </div>
    {/each}

    {#if suppliers.length === 0}
        <div class="rounded-xl border border-dashed p-8 text-center bg-card/40">
            <Truck class="mx-auto h-10 w-10 text-muted-foreground/40 mb-2" />
            <p class="text-xs text-muted-foreground font-medium">No se encontraron proveedores.</p>
            {#if hasPermission('proveedores.crear')}
                <Button size="sm" class="mt-3 gap-1.5 min-h-[44px] text-sm font-medium" onclick={onOpenCreate}>
                    <Plus class="h-3.5 w-3.5" />
                    Añadir Proveedor
                </Button>
            {/if}
        </div>
    {/if}

    <AppPagination {pagination} />
</div>
