<script lang="ts">
    import { Button } from '@/components/ui/button';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import UserCheck from 'lucide-svelte/icons/user-check';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Calendar from 'lucide-svelte/icons/calendar';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import Plus from 'lucide-svelte/icons/plus';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import { hasPermission } from '@/lib/permissions';

    export interface CustomerItem {
        id: number;
        tenant_id: number;
        name: string;
        tax_id: string | null;
        tax_condition: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        province?: string | null;
        city?: string | null;
        credit_limit: number | string;
        is_defaulter: boolean;
        is_active: boolean;
        notes: string | null;
        created_at?: string;
        updated_at?: string;
    }

    let {
        customers = [],
        onSelectCustomer,
        onOpenCreate,
    }: {
        customers: CustomerItem[];
        onSelectCustomer: (customer: CustomerItem) => void;
        onOpenCreate: () => void;
    } = $props();

    let page = $state(1);
    const perPage = 6;
    let paginatedCustomers = $derived(customers.slice((page - 1) * perPage, page * perPage));

    function formatTaxCondition(cond: string) {
        switch (cond) {
            case 'responsable_inscripto':
                return 'Resp. Inscripto';
            case 'monotributo':
                return 'Monotributo';
            case 'exento':
                return 'Exento';
            default:
                return 'Consumidor Final';
        }
    }

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
    {#each paginatedCustomers as customer (customer.id)}
        <div
            class="flex flex-col gap-2.5 rounded-xl border border-border/70 bg-card p-4 shadow-sm transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-accent/25 cursor-pointer"

            onclick={() => onSelectCustomer(customer)}
            onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelectCustomer(customer)}
            role="button"
            tabindex="0"
            aria-label={`Gestionar cliente ${customer.name}`}
        >
            <!-- Cabecera Mobile: Nombre + Estado + Icono Chevron -->
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0">
                    <IconContainer
                        size="sm"
                        rounded="lg"
                        variant={customer.is_defaulter ? 'warning' : 'primary'}
                        icon={customer.is_defaulter ? 'solar:danger-triangle-bold-duotone' : 'solar:shop-bold-duotone'}
                    />
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <h4 class="font-bold text-sm text-foreground truncate">
                                {customer.name}
                            </h4>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs font-semibold bg-muted px-1.5 py-0.5 rounded text-muted-foreground">
                                {formatTaxCondition(customer.tax_condition)}
                            </span>
                            {#if customer.created_at}
                                <span class="text-xs font-mono tabular-nums text-muted-foreground flex items-center gap-1">
                                    <Calendar class="size-3" />
                                    {formatDate(customer.created_at)}
                                </span>
                            {/if}
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                    {#if customer.is_defaulter}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400">
                            Moroso
                        </span>
                    {/if}

                    {#if customer.is_active}
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

            <!-- CUIT, Teléfono y Email -->
            <div class="flex items-center gap-2 flex-wrap pt-1 border-t border-border/40">
                {#if customer.tax_id}
                    <span class="inline-flex items-center gap-1 text-xs font-mono tabular-nums bg-muted/60 px-2 py-0.5 rounded text-foreground font-semibold">
                        CUIT: {customer.tax_id}
                    </span>
                {/if}
                {#if customer.phone}
                    <span class="inline-flex items-center gap-1 text-xs tabular-nums bg-muted/60 px-2 py-0.5 rounded text-foreground">
                        <Phone class="size-3 text-muted-foreground" />
                        {customer.phone}
                    </span>
                {/if}
                {#if customer.email}
                    <span class="inline-flex items-center gap-1 text-xs bg-muted/60 px-2 py-0.5 rounded text-foreground truncate max-w-[180px]">
                        <Mail class="size-3 text-muted-foreground" />
                        {customer.email}
                    </span>
                {/if}
            </div>

            <!-- Dirección -->
            {#if customer.address}
                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <MapPin class="size-3.5 shrink-0 text-muted-foreground/70" />
                    <span class="truncate">{customer.address}</span>
                </div>
            {/if}
        </div>
    {/each}

    {#if customers.length === 0}
        <div class="rounded-xl border border-dashed p-8 text-center bg-card/40">
            <UserCheck class="mx-auto h-10 w-10 text-muted-foreground/40 mb-2" />
            <p class="text-xs text-muted-foreground font-medium">No se encontraron clientes.</p>
            {#if hasPermission('clientes.crear')}
                <Button class="mt-3 gap-1.5 min-h-[44px] text-sm font-medium rounded-xl" onclick={onOpenCreate}>
                    <Plus class="h-3.5 w-3.5" />
                    Añadir Cliente
                </Button>
            {/if}
        </div>
    {/if}

    {#if customers.length > perPage}
        <AppPagination count={customers.length} {perPage} bind:page />
    {/if}
</div>
