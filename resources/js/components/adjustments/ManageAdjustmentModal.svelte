<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Textarea } from '@/components/ui/textarea';
    import { useForm } from '@inertiajs/svelte';
    import MinusCircle from 'lucide-svelte/icons/minus-circle';
    import PlusCircle from 'lucide-svelte/icons/plus-circle';
    import Percent from 'lucide-svelte/icons/percent';
    import DollarSign from 'lucide-svelte/icons/dollar-sign';
    import Tag from 'lucide-svelte/icons/tag';
    import FileText from 'lucide-svelte/icons/file-text';
    import AdjustmentCalendarPicker from './AdjustmentCalendarPicker.svelte';
    import { preventInvalidNumericKeys, sanitizeNumericInput } from '@/lib/numericSanitizer';

    export interface PriceAdjustmentItem {
        id: number;
        tenant_id: number;
        name: string;
        type: 'discount' | 'surcharge';
        calculation_type: 'fixed' | 'percentage';
        value: number | string;
        reason?: string | null;
        starts_at?: string | null;
        ends_at?: string | null;
        days_of_week?: number[] | null;
        specific_dates?: string[] | null;
        is_active: boolean;
        created_at?: string;
    }

    let {
        open = $bindable(false),
        adjustment = null,
        onclose,
    }: {
        open?: boolean;
        adjustment?: PriceAdjustmentItem | null;
        onclose?: () => void;
    } = $props();

    const form = useForm({
        name: '',
        type: 'discount' as 'discount' | 'surcharge',
        calculation_type: 'percentage' as 'fixed' | 'percentage',
        value: 10,
        reason: '',
        starts_at: '',
        ends_at: '',
        days_of_week: [] as number[],
        specific_dates: [] as string[],
        is_active: true,
    });

    let lastLoadedId = $state<number | null>(null);

    $effect(() => {
        if (open && adjustment && adjustment.id !== lastLoadedId) {
            lastLoadedId = adjustment.id;
            form.name = adjustment.name || '';
            form.type = adjustment.type;
            form.calculation_type = adjustment.calculation_type;
            form.value = Number(adjustment.value) || 0;
            form.reason = adjustment.reason || '';
            form.starts_at = adjustment.starts_at ? adjustment.starts_at.slice(0, 10) : '';
            form.ends_at = adjustment.ends_at ? adjustment.ends_at.slice(0, 10) : '';
            form.days_of_week = Array.isArray(adjustment.days_of_week) ? adjustment.days_of_week : [];
            form.specific_dates = Array.isArray(adjustment.specific_dates) ? adjustment.specific_dates : [];
            form.is_active = Boolean(adjustment.is_active);
            form.clearErrors();
        } else if (open && !adjustment && lastLoadedId !== null) {
            lastLoadedId = null;
            form.reset();
            form.name = '';
            form.type = 'discount';
            form.calculation_type = 'percentage';
            form.value = 10;
            form.reason = '';
            form.starts_at = '';
            form.ends_at = '';
            form.days_of_week = [];
            form.specific_dates = [];
            form.is_active = true;
            form.clearErrors();
        } else if (!open) {
            lastLoadedId = null;
        }
    });

    function handleClose() {
        open = false;
        if (onclose) onclose();
    }

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();

        if (adjustment) {
            form.put(`/tenant/configuracion/recargos-descuentos/${adjustment.id}`, {
                onSuccess: () => handleClose(),
            });
        } else {
            form.post('/tenant/configuracion/recargos-descuentos', {
                onSuccess: () => handleClose(),
            });
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto p-5 sm:p-6 space-y-4">
        <DialogHeader>
            <DialogTitle class="text-lg font-bold text-foreground">
                {adjustment ? 'Editar Recargo / Descuento' : 'Nuevo Recargo o Descuento'}
            </DialogTitle>
            <DialogDescription class="sr-only">
                {adjustment ? 'Editar configuración de recargo o descuento' : 'Crear nuevo recargo o descuento'}
            </DialogDescription>
        </DialogHeader>

        <form onsubmit={handleSubmit} class="space-y-4 w-full min-w-0 max-w-full">
            <!-- Tipo y Tipo de Cálculo -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <!-- Tipo: Descuento o Recargo -->
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold">Tipo de ajuste</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onclick={() => form.type = 'discount'}
                            class={`min-h-[44px] flex items-center justify-center gap-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer ${
                                form.type === 'discount'
                                    ? 'bg-emerald-500 text-white border-emerald-600 shadow-2xs'
                                    : 'bg-background hover:bg-muted text-foreground border-border/80'
                            }`}
                        >
                            <MinusCircle class="h-4 w-4" />
                            <span>Descuento</span>
                        </button>
                        <button
                            type="button"
                            onclick={() => form.type = 'surcharge'}
                            class={`min-h-[44px] flex items-center justify-center gap-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer ${
                                form.type === 'surcharge'
                                    ? 'bg-amber-600 text-white border-amber-700 shadow-2xs'
                                    : 'bg-background hover:bg-muted text-foreground border-border/80'
                            }`}
                        >
                            <PlusCircle class="h-4 w-4" />
                            <span>Recargo</span>
                        </button>
                    </div>
                </div>

                <!-- Tipo de Cálculo: Porcentaje o Monto Fijo -->
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold">Modalidad de cálculo</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onclick={() => {
                                form.calculation_type = 'percentage';
                                if (Number(form.value) > 100) form.value = 100;
                            }}
                            class={`min-h-[44px] flex items-center justify-center gap-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer ${
                                form.calculation_type === 'percentage'
                                    ? 'bg-primary text-primary-foreground border-primary shadow-2xs'
                                    : 'bg-background hover:bg-muted text-foreground border-border/80'
                            }`}
                        >
                            <Percent class="h-4 w-4" />
                            <span>Porcentaje</span>
                        </button>
                        <button
                            type="button"
                            onclick={() => {
                                form.calculation_type = 'fixed';
                                if (Number(form.value) > 100_000_000) form.value = 100_000_000;
                            }}
                            class={`min-h-[44px] flex items-center justify-center gap-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer ${
                                form.calculation_type === 'fixed'
                                    ? 'bg-primary text-primary-foreground border-primary shadow-2xs'
                                    : 'bg-background hover:bg-muted text-foreground border-border/80'
                            }`}
                        >
                            <DollarSign class="h-4 w-4" />
                            <span>Monto fijo</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Nombre y Valor -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <div class="sm:col-span-2 space-y-1.5">
                    <Label for="adjustment-name" class="text-xs font-semibold">Nombre identificativo (opcional)</Label>
                    <Input
                        id="adjustment-name"
                        bind:value={form.name}
                        placeholder="ej. Promoción comercial (default: Sin Nombre)"
                        class="min-h-[40px] text-sm font-medium"
                    />
                    {#if form.errors.name}
                        <p class="text-xs text-destructive">{form.errors.name}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="adjustment-value" class="text-xs font-semibold">
                        Valor
                    </Label>
                    <div class="relative">
                        <Input
                            id="adjustment-value"
                            type="number"
                            step={form.calculation_type === 'percentage' ? '0.01' : '1'}
                            min="0.01"
                            max={form.calculation_type === 'percentage' ? '100' : '100000000'}
                            value={form.value}
                            onkeydown={(e) => preventInvalidNumericKeys(e)}
                            oninput={(e) => {
                                const max = form.calculation_type === 'percentage' ? 100 : 100_000_000;
                                sanitizeNumericInput(e, 0.01, max, (val) => {
                                    form.value = typeof val === 'number' ? val : (Number(val) || 0);
                                });
                            }}
                            required
                            class="min-h-[40px] text-sm font-bold tabular-nums pr-8"
                        />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted-foreground pointer-events-none">
                            {form.calculation_type === 'percentage' ? '%' : '$'}
                        </span>
                    </div>
                    {#if form.errors.value}
                        <p class="text-xs text-destructive">{form.errors.value}</p>
                    {/if}
                </div>
            </div>

            <!-- Motivo Opcional -->
            <div class="space-y-1.5">
                <Label for="adjustment-reason" class="text-xs font-semibold flex items-center gap-1.5">
                    <FileText class="h-3.5 w-3.5 text-muted-foreground" />
                    Motivo o justificación interna (opcional)
                </Label>
                <Input
                    id="adjustment-reason"
                    bind:value={form.reason}
                    placeholder="ej. Descuento comercial por pronto pago"
                    class="min-h-[40px] text-sm"
                />
                {#if form.errors.reason}
                    <p class="text-xs text-destructive">{form.errors.reason}</p>
                {/if}
            </div>

            <!-- Selector de Calendario y Días de la Semana -->
            <AdjustmentCalendarPicker
                bind:specificDates={form.specific_dates}
                bind:startsAt={form.starts_at}
                bind:endsAt={form.ends_at}
                bind:daysOfWeek={form.days_of_week}
                disabled={form.processing}
            />

            <DialogFooter class="grid grid-cols-2 gap-2.5 sm:flex sm:justify-end pt-2">
                <Button
                    type="button"
                    variant="outline"
                    onclick={handleClose}
                    disabled={form.processing}
                    class="min-h-[44px] text-xs font-medium"
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    disabled={form.processing}
                    class="min-h-[44px] text-xs font-semibold shadow-xs"
                >
                    {#if form.processing}
                        Guardando...
                    {:else}
                        {adjustment ? 'Guardar Cambios' : 'Crear Ajuste'}
                    {/if}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>
