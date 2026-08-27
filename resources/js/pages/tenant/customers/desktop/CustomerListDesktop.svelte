<script lang="ts">
    import { Button } from '@/components/ui/button';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import UserCheck from 'lucide-svelte/icons/user-check';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Calendar from 'lucide-svelte/icons/calendar';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';

    export interface CustomerItem {
        id: number;
        tenant_id: number;
        name: string;
        tax_id: string | null;
        tax_condition: string;
        email: string | null;
        phone: string | null;
        address: string | null;
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
    {#each paginatedCustomers as customer (customer.id)}
        <div
            role="button"
            tabindex="0"
            onclick={() => onSelectCustomer(customer)}
            onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelectCustomer(customer)}
            class="group flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-2xl border transition-all duration-200 gap-4 cursor-pointer select-none bg-card shadow-xs hover:shadow-md hover:border-border {customer.is_defaulter
                ? 'border-rose-300 dark:border-rose-800/60 bg-rose-50/15 dark:bg-rose-950/10'
                : 'border-border/70 hover:bg-muted/10'}"
        >
            <div class="flex items-start sm:items-center gap-4 min-w-0 flex-1">
                <!-- Avatar / Icono -->
                <div class="h-12 w-12 rounded-xl flex items-center justify-center shrink-0 border transition-transform group-hover:scale-105 {customer.is_defaulter
                    ? 'bg-rose-500/10 text-rose-600 border-rose-500/20'
                    : 'bg-primary/10 text-primary border-primary/20'}">
                    {#if customer.is_defaulter}
                        <AlertTriangle class="h-6 w-6 stroke-[1.75]" />
                    {:else}
                        <UserCheck class="h-6 w-6 stroke-[1.75]" />
                    {/if}
                </div>

                <!-- Detalle y Metadatos -->
                <div class="flex flex-col gap-1.5 min-w-0 flex-1">
                    <!-- Fila 1: Nombre Destacado + Badges -->
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h3 class="font-bold text-base text-foreground group-hover:text-primary transition-colors truncate">
                            {customer.name}
                        </h3>

                        {#if customer.is_defaulter}
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20 shadow-2xs">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                Moroso / Saldo Pendiente
                            </span>
                        {/if}

                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-muted/60 text-muted-foreground border border-border/50">
                            {formatTaxCondition(customer.tax_condition)}
                        </span>

                        {#if customer.is_active}
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

                    <!-- Fila 2: CUIT/DNI, Teléfono y Email -->
                    <div class="flex items-center gap-2.5 flex-wrap text-xs text-muted-foreground">
                        {#if customer.tax_id}
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-muted/60 px-2 py-0.5 text-xs font-medium text-foreground/90 border border-border/40 font-mono tabular-nums">
                                CUIT: {customer.tax_id}
                            </span>
                        {/if}

                        {#if customer.phone}
                            <div class="flex items-center gap-1">
                                <Phone class="h-3 w-3 text-muted-foreground/70" />
                                <span class="font-mono tabular-nums">{customer.phone}</span>
                            </div>
                        {/if}

                        {#if customer.email}
                            <div class="flex items-center gap-1">
                                <Mail class="h-3 w-3 text-muted-foreground/70" />
                                <span class="truncate max-w-[200px]">{customer.email}</span>
                            </div>
                        {/if}

                        {#if customer.address}
                            <div class="flex items-center gap-1">
                                <MapPin class="h-3 w-3 text-muted-foreground/70" />
                                <span class="truncate max-w-[180px]">{customer.address}</span>
                            </div>
                        {/if}

                        {#if Number(customer.credit_limit) > 0}
                            <div class="flex items-center gap-1">
                                <CreditCard class="h-3 w-3 text-primary" />
                                <span class="tabular-nums">Límite: <strong class="text-foreground">{formatCurrency(customer.credit_limit)}</strong></span>
                            </div>
                        {/if}

                        {#if customer.created_at}
                            <div class="flex items-center gap-1 shrink-0 ml-auto sm:ml-0 tabular-nums">
                                <Calendar class="h-3 w-3 text-muted-foreground/70" />
                                <span>Alta: {formatDate(customer.created_at)}</span>
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

    {#if customers.length === 0}
        <EmptyState
            icon={UserCheck}
            title="No se encontraron clientes"
            description="No hay clientes que coincidan con la búsqueda o todavía no has registrado clientes en tu comercio."
            actionLabel="Añadir Primer Cliente"
            onaction={onOpenCreate}
        />
    {/if}

    {#if customers.length > perPage}
        <AppPagination count={customers.length} {perPage} bind:page />
    {/if}
</div>
