<script lang="ts">
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        DialogBody,
        DialogFooter,
    } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { router } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import { generateUUID } from '@/lib/utils';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Check from 'lucide-svelte/icons/check';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import Search from 'lucide-svelte/icons/search';
    import Sliders from 'lucide-svelte/icons/sliders';
    import Tag from 'lucide-svelte/icons/tag';
    import Percent from 'lucide-svelte/icons/percent';
    import DollarSign from 'lucide-svelte/icons/dollar-sign';

    interface PresetAdjustment {
        id: number;
        name: string;
        type: 'discount' | 'surcharge';
        calculation_type: 'percentage' | 'fixed';
        value: number | string;
        reason?: string | null;
        is_active: boolean;
        is_valid_today?: boolean;
    }

    interface Props {
        open?: boolean;
        orderId?: number | null;
        netProductsAmount?: number;
        orderDate?: string | null;
        onSuccess?: (updatedOrder: any) => void;
        onApplyLocal?: (adjustments: any[]) => void;
        onBack?: () => void;
        onclose?: () => void;
    }

    let {
        open = $bindable(false),
        orderId = null,
        netProductsAmount = 0,
        orderDate = null,
        onSuccess = undefined,
        onApplyLocal = undefined,
        onBack = undefined,
        onclose = undefined,
    }: Props = $props();

    function handleBack() {
        open = false;
        onBack?.();
    }

    let adjustments = $state<PresetAdjustment[]>([]);
    let isLoading = $state(false);
    let isSubmitting = $state(false);
    let searchQuery = $state('');
    let selectedIds = $state<number[]>([]);
    let errorMessage = $state<string | null>(null);

    // Fetch active adjustments on open
    $effect(() => {
        if (open) {
            fetchAdjustments();
            searchQuery = '';
            selectedIds = [];
            errorMessage = null;
        }
    });

    async function fetchAdjustments() {
        isLoading = true;
        errorMessage = null;
        try {
            const url = orderDate
                ? `/tenant/configuracion/recargos-descuentos/activos?date=${encodeURIComponent(orderDate)}`
                : '/tenant/configuracion/recargos-descuentos/activos';
            const res = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                },
            });
            if (!res.ok) {
                throw new Error('No se pudieron cargar los ajustes comerciales.');
            }
            const data = await res.json();
            adjustments = Array.isArray(data) ? data : [];
        } catch (err: any) {
            errorMessage = err?.message || 'Error al cargar los recargos y descuentos.';
        } finally {
            isLoading = false;
        }
    }

    const filteredAdjustments = $derived.by(() => {
        const query = searchQuery.trim().toLowerCase();
        if (!query) return adjustments;
        return adjustments.filter(
            (adj) =>
                adj.name.toLowerCase().includes(query) ||
                (adj.reason && adj.reason.toLowerCase().includes(query))
        );
    });

    function toggleSelect(id: number) {
        if (selectedIds.includes(id)) {
            selectedIds = selectedIds.filter((item) => item !== id);
        } else {
            selectedIds = [...selectedIds, id];
        }
    }

    function formatCurrency(amount: number | string): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount));
    }

    function calculatePreviewAmount(adj: PresetAdjustment): number {
        const val = Number(adj.value) || 0;
        if (adj.calculation_type === 'percentage') {
            return Math.round(netProductsAmount * (val / 100) * 100) / 100;
        }
        return Math.round(val * 100) / 100;
    }

    function handleClose() {
        open = false;
        errorMessage = null;
        onclose?.();
    }

    function handleApply() {
        if (selectedIds.length === 0 || isSubmitting) return;

        const selectedList = adjustments.filter((adj) => selectedIds.includes(adj.id));
        if (selectedList.length === 0) return;

        const mappedAdjustments = selectedList.map((adj) => {
            const val = Number(adj.value) || 0;
            const applied = calculatePreviewAmount(adj);
            return {
                id: generateUUID(),
                source: 'preset',
                source_id: adj.id,
                type: adj.type,
                name: adj.name,
                calculation_type: adj.calculation_type,
                value: val,
                applied_amount: applied,
                reason: adj.reason || null,
                show_in_receipt: true,
            };
        });

        if (onApplyLocal) {
            onApplyLocal(mappedAdjustments);
            toast.success(
                mappedAdjustments.length === 1
                    ? 'Ajuste agregado al pedido.'
                    : `${mappedAdjustments.length} ajustes agregados al pedido.`
            );
            handleClose();
            return;
        }

        if (!orderId) return;

        isSubmitting = true;
        errorMessage = null;

        router.post(
            `/tenant/pedidos/${orderId}/ajustes`,
            {
                adjustments: mappedAdjustments.map((a) => ({
                    source: 'preset',
                    source_id: a.source_id,
                    type: a.type,
                    name: a.name,
                    calculation_type: a.calculation_type,
                    value: a.value,
                    reason: a.reason,
                    show_in_receipt: true,
                })),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (pageProps: any) => {
                    toast.success(
                        mappedAdjustments.length === 1
                            ? 'Ajuste aplicado correctamente al pedido.'
                            : `${mappedAdjustments.length} ajustes aplicados correctamente.`
                    );
                    onSuccess?.(pageProps?.props?.order);
                    handleClose();
                },
                onError: (errors) => {
                    errorMessage =
                        errors.order ||
                        Object.values(errors)[0] ||
                        'Error al aplicar los ajustes seleccionados.';
                },
                onFinish: () => {
                    isSubmitting = false;
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-xl p-5 sm:p-6 space-y-4 rounded-2xl max-h-[90vh] flex flex-col">
        <DialogHeader class="shrink-0 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <Sliders class="size-4" />
                </div>
                <DialogTitle class="text-lg font-bold text-foreground">
                    Seleccionar ajustes
                </DialogTitle>
            </div>
        </DialogHeader>

        <DialogBody class="py-2 space-y-3 flex-1 overflow-y-auto min-h-0">
            <!-- Buscador en tiempo real -->
            <div class="relative">
                <Search class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                <Input
                    type="search"
                    placeholder="Buscar por nombre o motivo..."
                    bind:value={searchQuery}
                    class="pl-9 min-h-[44px] rounded-xl text-xs"
                />
            </div>

            {#if errorMessage}
                <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-xs text-destructive font-medium">
                    {errorMessage}
                </div>
            {/if}

            {#if isLoading}
                <div class="py-12 flex flex-col items-center justify-center text-muted-foreground gap-2">
                    <Loader2 class="size-6 animate-spin text-primary" />
                    <span class="text-xs">Cargando catálogo de ajustes...</span>
                </div>
            {:else if filteredAdjustments.length === 0}
                <div class="py-10 text-center rounded-xl border border-dashed border-border/80 bg-muted/20 p-6">
                    <Tag class="size-8 mx-auto text-muted-foreground/60 mb-2" />
                    <p class="text-xs font-semibold text-foreground">
                        {searchQuery ? 'No se encontraron ajustes con ese criterio' : 'No hay ajustes comerciales activos'}
                    </p>
                    <p class="text-[11px] text-muted-foreground mt-1">
                        Podés configurar recargos y descuentos desde la configuración del comercio.
                    </p>
                </div>
            {:else}
                <div class="space-y-2">
                    {#each filteredAdjustments as adj (adj.id)}
                        {@const isSelected = selectedIds.includes(adj.id)}
                        {@const previewAmount = calculatePreviewAmount(adj)}
                        <label
                            class="flex items-start sm:items-center justify-between gap-3 p-3 rounded-xl border transition-all cursor-pointer {isSelected ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : 'border-border/70 bg-card hover:bg-muted/40'}"
                        >
                            <div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                                <input
                                    type="checkbox"
                                    checked={isSelected}
                                    onchange={() => toggleSelect(adj.id)}
                                    class="size-4 rounded border-border text-primary focus:ring-primary/20 shrink-0 mt-0.5 sm:mt-0 cursor-pointer"
                                />

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-xs font-bold text-foreground">
                                            {adj.name}
                                        </span>

                                        <!-- Badge Tipo -->
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-semibold {adj.type === 'discount' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'}">
                                            {adj.type === 'discount' ? 'Descuento' : 'Recargo'}
                                        </span>

                                        <!-- Badge Vigencia -->
                                        {#if adj.is_valid_today}
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                                                Aplica hoy
                                            </span>
                                        {:else}
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-muted text-muted-foreground">
                                                Programado
                                            </span>
                                        {/if}
                                    </div>

                                    {#if adj.reason}
                                        <p class="text-[11px] text-muted-foreground line-clamp-1 mt-0.5">
                                            {adj.reason}
                                        </p>
                                    {/if}
                                </div>
                            </div>

                            <!-- Valor y Previsualización -->
                            <div class="text-right shrink-0">
                                <div class="text-xs font-bold tabular-nums text-foreground">
                                    {adj.calculation_type === 'percentage' ? `${adj.value}%` : formatCurrency(adj.value)}
                                </div>
                                {#if netProductsAmount > 0}
                                    <div class="text-[11px] font-semibold tabular-nums {adj.type === 'discount' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}">
                                        {adj.type === 'discount' ? '-' : '+'}{formatCurrency(previewAmount)}
                                    </div>
                                {/if}
                            </div>
                        </label>
                    {/each}
                </div>
            {/if}
        </DialogBody>

        <DialogFooter class="grid grid-cols-2 gap-2.5 w-full sm:flex sm:justify-end sm:w-auto pt-2 border-t border-border/70 shrink-0">
            {#if onBack}
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-[44px] rounded-xl text-xs font-medium cursor-pointer gap-1.5"
                    disabled={isSubmitting}
                    onclick={handleBack}
                >
                    <ArrowLeft class="size-3.5" />
                    <span>Volver</span>
                </Button>
            {:else}
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-[44px] rounded-xl text-xs font-medium cursor-pointer"
                    disabled={isSubmitting}
                    onclick={handleClose}
                >
                    Cancelar
                </Button>
            {/if}
            <Button
                type="button"
                class="min-h-[44px] rounded-xl text-xs font-semibold gap-1.5 cursor-pointer"
                disabled={selectedIds.length === 0 || isSubmitting}
                onclick={handleApply}
            >
                {#if isSubmitting}
                    <Loader2 class="size-3.5 animate-spin" />
                    <span>Aplicando...</span>
                {:else}
                    <Check class="size-3.5" />
                    <span class="sm:hidden">Aplicar ({selectedIds.length})</span>
                    <span class="hidden sm:inline">Aplicar seleccionados ({selectedIds.length})</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
