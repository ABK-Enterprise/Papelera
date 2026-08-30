<script lang="ts">
    import AppDrawer from '@/components/common/AppDrawer.svelte';
    import { Button } from '@/components/ui/button';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Banknote from 'lucide-svelte/icons/banknote';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import Clock from 'lucide-svelte/icons/clock';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import History from 'lucide-svelte/icons/history';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import Sliders from 'lucide-svelte/icons/sliders';
    import User from 'lucide-svelte/icons/user';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import type { CustomerAccountFull } from '../desktop/AccountLedgerDesktop.svelte';

    let {
        customers = [],
        onOpenPayment,
        onOpenAdjustment,
        onOpenCreditSettings,
    }: {
        customers: CustomerAccountFull[];
        onOpenPayment: (cust: CustomerAccountFull) => void;
        onOpenAdjustment: (cust: CustomerAccountFull) => void;
        onOpenCreditSettings: (cust: CustomerAccountFull) => void;
    } = $props();

    let selectedCustomerForDrawer = $state<CustomerAccountFull | null>(null);
    let isDrawerOpen = $state(false);
    let isLoadingMovements = $state(false);

    async function openCustomerDrawer(cust: CustomerAccountFull) {
        selectedCustomerForDrawer = cust;
        isDrawerOpen = true;
        isLoadingMovements = true;
        try {
            const res = await fetch(`/tenant/customers/${cust.id}/account`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const fullData = await res.json();
                if (selectedCustomerForDrawer && selectedCustomerForDrawer.id === cust.id) {
                    selectedCustomerForDrawer = fullData;
                }
            }
        } catch (err) {
            console.error('Error loading customer account details:', err);
        } finally {
            isLoadingMovements = false;
        }
    }

    function formatPrice(val: number | string | null | undefined): string {
        const num = Number(val) || 0;
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(num);
    }

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return new Intl.DateTimeFormat('es-AR', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }).format(d);
    }
</script>

<div class="space-y-3">
    {#each customers as cust (cust.id)}
        {@const balanceNum = Number(cust.current_balance) || 0}
        {@const limitNum = Number(cust.credit_limit) || 0}
        {@const usagePercent = limitNum > 0 ? Math.min(100, Math.round((balanceNum / limitNum) * 100)) : 0}

        <button
            type="button"
            class="w-full text-left p-4 rounded-2xl border border-border/80 bg-card hover:bg-muted/15 transition-all shadow-2xs space-y-3 group"
            onclick={() => openCustomerDrawer(cust)}
        >
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <IconContainer size="md" variant="primary">
                        {cust.name.slice(0, 2).toUpperCase()}
                    </IconContainer>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <h3 class="font-bold text-sm text-foreground truncate group-hover:text-primary transition-colors">
                                {cust.name}
                            </h3>
                            {#if cust.is_defaulter}
                                <span class="text-[9px] font-bold text-amber-700 bg-amber-500/15 dark:text-amber-400 px-1.5 py-0.2 rounded">
                                    Moroso
                                </span>
                            {/if}
                        </div>
                        <p class="text-[11px] text-muted-foreground tabular-nums mt-0.5">
                            {cust.tax_id ? `CUIT ${cust.tax_id}` : 'Sin CUIT'}
                        </p>
                    </div>
                </div>

                <div class="text-right shrink-0">
                    <span class="text-sm font-bold tabular-nums block {balanceNum > 0 ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'}">
                        {formatPrice(balanceNum)}
                    </span>
                    <span class="text-[10px] text-muted-foreground">Saldo Deudor</span>
                </div>
            </div>

            <!-- Mini Barra de Crédito -->
            <div class="space-y-1 pt-1 border-t border-border/50">
                <div class="flex items-center justify-between text-[11px] text-muted-foreground tabular-nums">
                    <span>Límite: {formatPrice(limitNum)}</span>
                    <span class="font-medium text-foreground">Disp: {formatPrice(cust.available_credit ?? Math.max(0, limitNum - balanceNum))}</span>
                </div>
                <div class="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all {usagePercent > 90 ? 'bg-destructive' : usagePercent > 70 ? 'bg-amber-500' : 'bg-primary'}"
                        style="width: {usagePercent}%;"
                    ></div>
                </div>
            </div>

            <div class="flex items-center justify-between text-[11px] text-primary pt-0.5 font-medium">
                <span>Gestionar cuenta y cobros</span>
                <ChevronRight class="size-3.5" />
            </div>
        </button>
    {/each}

    {#if customers.length === 0}
        <div class="py-12 text-center rounded-2xl bg-card border border-border p-6 space-y-2">
            <CreditCard class="size-8 text-muted-foreground mx-auto opacity-50" />
            <h4 class="text-xs font-semibold text-foreground">No se encontraron clientes</h4>
            <p class="text-[11px] text-muted-foreground">
                Intenta modificar los términos de búsqueda o filtros.
            </p>
        </div>
    {/if}
</div>

<!-- Drawer de Detalle y Operaciones Mobile -->
<AppDrawer
    bind:open={isDrawerOpen}
    title={selectedCustomerForDrawer ? selectedCustomerForDrawer.name : 'Cuenta Corriente'}
    description={selectedCustomerForDrawer?.tax_id ? `CUIT ${selectedCustomerForDrawer.tax_id}` : 'Detalle de saldos y movimientos'}
>
    {#if selectedCustomerForDrawer}
        {@const balanceNum = Number(selectedCustomerForDrawer.current_balance) || 0}
        {@const limitNum = Number(selectedCustomerForDrawer.credit_limit) || 0}
        {@const usagePercent = limitNum > 0 ? Math.min(100, Math.round((balanceNum / limitNum) * 100)) : 0}

        <div class="space-y-4">
            <!-- Saldo y Límites -->
            <div class="p-4 rounded-2xl bg-muted/40 border border-border/70 space-y-3">
                <div class="flex items-baseline justify-between">
                    <div>
                        <span class="text-xs text-muted-foreground block">Saldo deudor actual:</span>
                        <span class="text-2xl font-bold tabular-nums {balanceNum > 0 ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'}">
                            {formatPrice(balanceNum)}
                        </span>
                    </div>
                    {#if selectedCustomerForDrawer.is_defaulter}
                        <span class="text-xs font-bold text-amber-700 bg-amber-500/15 dark:text-amber-400 px-2 py-0.5 rounded-full flex items-center gap-1">
                            <AlertTriangle class="size-3.5" />
                            Moroso
                        </span>
                    {/if}
                </div>

                <div class="space-y-1 pt-2 border-t border-border/60">
                    <div class="flex items-center justify-between text-xs text-muted-foreground tabular-nums">
                        <span>Límite: {formatPrice(limitNum)}</span>
                        <span>Disponible: <strong class="text-foreground">{formatPrice(selectedCustomerForDrawer.available_credit ?? Math.max(0, limitNum - balanceNum))}</strong></span>
                    </div>
                    <div class="h-2 w-full bg-muted rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full {usagePercent > 90 ? 'bg-destructive' : usagePercent > 70 ? 'bg-amber-500' : 'bg-primary'}"
                            style="width: {usagePercent}%;"
                        ></div>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 text-xs text-muted-foreground pt-1 border-t border-border/60">
                    <Clock class="size-3.5 text-primary" />
                    <span>Plazo de pago: <strong class="text-foreground tabular-nums">{selectedCustomerForDrawer.credit_due_days || 30} días</strong></span>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="space-y-2">
                <Button
                    onclick={() => {
                        const cust = selectedCustomerForDrawer;
                        isDrawerOpen = false;
                        if (cust) onOpenPayment(cust);
                    }}
                    class="w-full h-11 gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm"
                >
                    <Banknote class="size-4" />
                    <span>Registrar Cobro / Abono</span>
                </Button>

                <div class="grid grid-cols-2 gap-2">
                    <Button
                        variant="secondary"
                        onclick={() => {
                            const cust = selectedCustomerForDrawer;
                            isDrawerOpen = false;
                            if (cust) onOpenAdjustment(cust);
                        }}
                        class="h-10 text-xs font-medium rounded-xl gap-1.5"
                    >
                        <Sliders class="size-3.5" />
                        <span>Ajustar Saldo</span>
                    </Button>

                    <Button
                        variant="outline"
                        onclick={() => {
                            const cust = selectedCustomerForDrawer;
                            isDrawerOpen = false;
                            if (cust) onOpenCreditSettings(cust);
                        }}
                        class="h-10 text-xs font-medium rounded-xl gap-1.5"
                    >
                        <ShieldCheck class="size-3.5" />
                        <span>Límites</span>
                    </Button>
                </div>
            </div>

            <!-- Movimientos Recientes -->
            <div class="space-y-2 pt-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                        <History class="size-3.5 text-primary" />
                        Últimos Movimientos
                    </h4>
                    {#if isLoadingMovements}
                        <Loader2 class="size-3.5 animate-spin text-muted-foreground" />
                    {/if}
                </div>

                {#if isLoadingMovements && (!selectedCustomerForDrawer.movements || selectedCustomerForDrawer.movements.length === 0)}
                    <div class="py-6 text-center text-xs text-muted-foreground flex items-center justify-center gap-2">
                        <Loader2 class="size-4 animate-spin text-primary" />
                        <span>Cargando movimientos...</span>
                    </div>
                {:else if selectedCustomerForDrawer.movements && selectedCustomerForDrawer.movements.length > 0}
                    <div class="space-y-2">
                        {#each selectedCustomerForDrawer.movements as mov (mov.id)}
                            {@const isPayment = mov.type === 'payment'}
                            {@const isCharge = mov.type === 'charge'}

                            <div class="p-3 rounded-xl border bg-card text-xs space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-foreground truncate pr-2">
                                        {mov.description}
                                    </span>
                                    <span class="font-bold tabular-nums {isPayment ? 'text-emerald-600 dark:text-emerald-400' : isCharge ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'}">
                                        {isPayment ? '-' : '+'}{formatPrice(mov.amount)}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-muted-foreground tabular-nums">
                                    <span>{formatDate(mov.created_at)}</span>
                                    <span>Saldo: <strong class="text-foreground">{formatPrice(mov.balance_after)}</strong></span>
                                </div>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <p class="text-xs text-muted-foreground italic py-3 text-center">
                        Sin movimientos registrados.
                    </p>
                {/if}
            </div>
        </div>
    {/if}
</AppDrawer>
