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
    import CreditCard from 'lucide-svelte/icons/credit-card';
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
    const perPage = 10;
    let paginatedCustomers = $derived(customers.slice((page - 1) * perPage, page * perPage));

    function formatCurrency(val: number | string) {
        const num = Number(val) || 0;
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(num);
    }

    function formatTaxCondition(cond: string) {
        switch (cond) {
            case 'responsable_inscripto':
                return 'Resp. Inscripto';
            case 'monotributo':
                return 'Monotributo';
            case 'exento':
                return 'Exento IVA';
            default:
                return 'Consumidor Final';
        }
    }

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
    {#each paginatedCustomers as customer (customer.id)}
        <div
            class="bento-card group flex items-center justify-between gap-6 rounded-xl border border-border/60 bg-card p-5 shadow-[0px_10px_30px_rgba(0,0,0,0.02)] transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-accent/25 hover:border-primary/40 cursor-pointer"

            onclick={() => onSelectCustomer(customer)}
            onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelectCustomer(customer)}
            role="button"
            tabindex="0"
            aria-label={`Gestionar cliente ${customer.name}`}
        >
            <!-- Lado Izquierdo: Icono + Información Principal (Wide Hierarchy) -->
            <div class="flex items-center gap-4.5 min-w-0 flex-1">
                <!-- Avatar Iniciales -->
                <IconContainer
                    size="xl"
                    variant={customer.is_defaulter ? 'warning' : 'primary'}
                    icon={customer.is_defaulter ? 'solar:danger-triangle-bold-duotone' : 'solar:shop-bold-duotone'}
                    class="transition-transform group-hover:scale-105"
                />

                <!-- Detalle y Metadatos -->
                <div class="flex flex-col gap-1.5 min-w-0 flex-1">
                    <!-- Fila 1: Nombre Destacado + Badges de Estado y Morosidad -->
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h3 class="font-bold text-lg text-foreground group-hover:text-primary transition-colors truncate">
                            {customer.name}
                        </h3>

                        {#if customer.is_defaulter}
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 border border-rose-200/60 shadow-2xs">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                Moroso / Saldo Pendiente
                            </span>
                        {/if}

                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-muted text-muted-foreground border border-border/50">
                            {formatTaxCondition(customer.tax_condition)}
                        </span>

                        {#if customer.is_active}
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/50">
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

                    <!-- Fila 2: CUIT/DNI, Teléfono y Email -->
                    <div class="flex items-center gap-2.5 flex-wrap">
                        {#if customer.tax_id}
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-muted/60 px-2.5 py-1 text-xs font-medium text-foreground/90 border border-border/40 font-mono tabular-nums">
                                <strong>CUIT/DNI:</strong> {customer.tax_id}
                            </span>
                        {/if}

                        {#if customer.phone}
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-muted/60 px-2.5 py-1 text-xs font-medium text-foreground/90 border border-border/40 tabular-nums">
                                <Phone class="size-3.5 text-muted-foreground" />
                                {customer.phone}
                            </span>
                        {/if}

                        {#if customer.email}
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-muted/60 px-2.5 py-1 text-xs font-medium text-foreground/90 border border-border/40">
                                <Mail class="size-3.5 text-muted-foreground" />
                                {customer.email}
                            </span>
                        {/if}

                        {#if Number(customer.credit_limit) > 0}
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-600 dark:text-blue-400 border border-blue-500/20 tabular-nums">
                                <CreditCard class="size-3.5" />
                                Límite: {formatCurrency(customer.credit_limit)}
                            </span>
                        {/if}
                    </div>

                    <!-- Fila 3: Dirección, Notas y Fecha de Alta -->
                    <div class="flex items-center gap-4 text-xs text-muted-foreground mt-0.5 flex-wrap">
                        {#if customer.address}
                            <div class="flex items-center gap-1 truncate max-w-md">
                                <MapPin class="size-3.5 shrink-0 text-muted-foreground/70" />
                                <span class="truncate">{customer.address}</span>
                            </div>
                        {/if}

                        {#if customer.notes}
                            <span class="truncate italic text-muted-foreground/80 max-w-sm">
                                "{customer.notes}"
                            </span>
                        {/if}

                        {#if customer.created_at}
                            <div class="flex items-center gap-1 shrink-0 ml-auto sm:ml-0">
                                <Calendar class="size-3.5 text-muted-foreground/70" />
                                <span class="text-xs font-mono tabular-nums">Alta: {formatDate(customer.created_at)}</span>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Lado Derecho: Acción Rápida -->
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

    {#if customers.length === 0}
        <div class="rounded-xl border border-dashed p-12 text-center bg-card/50">
            <UserCheck class="mx-auto h-12 w-12 text-muted-foreground/40 mb-3" />
            <h3 class="font-semibold text-lg text-foreground">No se encontraron clientes</h3>
            <p class="text-sm text-muted-foreground mt-1 max-w-sm mx-auto">
                No hay clientes que coincidan con la búsqueda o todavía no has registrado clientes en tu comercio.
            </p>
            {#if hasPermission('clientes.crear')}
                <Button class="mt-4 gap-2 min-h-[38px] text-sm font-medium rounded-xl" onclick={onOpenCreate}>
                    <Plus class="h-4 w-4" />
                    Añadir Primer Cliente
                </Button>
            {/if}
        </div>
    {/if}

    {#if customers.length > perPage}
        <AppPagination count={customers.length} {perPage} bind:page />
    {/if}
</div>
