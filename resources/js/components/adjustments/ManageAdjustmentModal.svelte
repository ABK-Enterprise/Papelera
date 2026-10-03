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
    import Percent from 'lucide-svelte/icons/percent';
    import DollarSign from 'lucide-svelte/icons/dollar-sign';
    import Tag from 'lucide-svelte/icons/tag';
    import FileText from 'lucide-svelte/icons/file-text';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import AdjustmentCalendarPicker from './AdjustmentCalendarPicker.svelte';

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
            <DialogTitle class="text-lg font-bold flex items-center gap-2">
                <div class="h-8 w-8 rounded-lg bg-primary-500/10 text-primary-600 flex items-center justify-center">
                    <Sparkles class="h-4 w-4" />
                </div>
                {adjustment ? 'Editar Recargo / Descuento' : 'Nuevo Recargo o Descuento'}
            </DialogTitle>
            <DialogDescription class="text-xs text-muted-foreground">
                Configura ajustes automáticos para pedidos con reglas de vigencia temporal y días de la semana.
            </DialogDescription>
        </DialogHeader>

        <form onsubmit={handleSubmit} class="space-y-4">
            <!-- Tipo y Tipo de Cálculo -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <!-- Tipo: Descuento o Recargo -->
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold">Tipo de Ajuste</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onclick={() => form.type = 'discount'}
                            class={`min-h-[40px] flex items-center justify-center gap-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer ${
                                form.type === 'discount'
                                    ? 'bg-emerald-500 text-white border-emerald-600 shadow-xs'
                                    : 'bg-background hover:bg-muted text-foreground border-border/80'
                            }`}
                        >
                            <Percent class="h-3.5 w-3.5" />
                            Descuento
                        </button>
                        <button
                            type="button"
                            onclick={() => form.type = 'surcharge'}
                            class={`min-h-[40px] flex items-center justify-center gap-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer ${
                                form.type === 'surcharge'
                                    ? 'bg-amber-600 text-white border-amber-700 shadow-xs'
                                    : 'bg-background hover:bg-muted text-foreground border-border/80'
                            }`}
                        >
                            <DollarSign class="h-3.5 w-3.5" />
                            Recargo
                        </button>
                    </div>
                </div>

                <!-- Tipo de Cálculo: Porcentual o Fijo -->
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold">Modalidad de Cálculo</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onclick={() => {
                                form.calculation_type = 'percentage';
                                if (Number(form.value) > 100) form.value = 100;
                            }}
                            class={`min-h-[40px] flex items-center justify-center gap-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer ${
                                form.calculation_type === 'percentage'
                                    ? 'bg-primary text-primary-foreground border-primary shadow-xs'
                                    : 'bg-background hover:bg-muted text-foreground border-border/80'
                            }`}
                        >
                            Porcentual (%)
                        </button>
                        <button
                            type="button"
                            onclick={() => form.calculation_type = 'fixed'}
                            class={`min-h-[40px] flex items-center justify-center gap-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer ${
                                form.calculation_type === 'fixed'
                                    ? 'bg-primary text-primary-foreground border-primary shadow-xs'
                                    : 'bg-background hover:bg-muted text-foreground border-border/80'
                            }`}
                        >
                            Monto Fijo ($)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Nombre y Valor -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <div class="sm:col-span-2 space-y-1.5">
                    <Label for="adjustment-name" class="text-xs font-semibold">Nombre identificativo (Opcional)</Label>
                    <Input
                        id="adjustment-name"
                        bind:value={form.name}
                        placeholder="ej. Descuento Miércoles de Clientes (default: Sin Nombre)"
                        class="h-10 text-sm font-medium"
                    />
                    {#if form.errors.name}
                        <p class="text-xs text-destructive">{form.errors.name}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="adjustment-value" class="text-xs font-semibold">
                        Valor {form.calculation_type === 'percentage' ? '(%)' : '($)'}
                    </Label>
                    <div class="relative">
                        <Input
                            id="adjustment-value"
                            type="number"
                            step={form.calculation_type === 'percentage' ? '0.01' : '1'}
                            min="0.01"
                            max={form.calculation_type === 'percentage' ? '100' : undefined}
                            bind:value={form.value}
                            required
                            class="h-10 text-sm font-bold tabular-nums pr-8"
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
                    Motivo o justificación interna (Opcional)
                </Label>
                <Input
                    id="adjustment-reason"
                    bind:value={form.reason}
                    placeholder="ej. Promoción comercial fin de mes por pago al contado"
                    class="h-10 text-sm"
                />
                {#if form.errors.reason}
                    <p class="text-xs text-destructive">{form.errors.reason}</p>
                {/if}
            </div>

            <!-- Selector de Calendario y Días de la Semana -->
            <AdjustmentCalendarPicker
                bind:startsAt={form.starts_at}
                bind:endsAt={form.ends_at}
                bind:daysOfWeek={form.days_of_week}
                disabled={form.processing}
            />

            <!-- Switch Activo -->
            <div class="flex items-center justify-between p-3 rounded-xl border border-border/70 bg-muted/20">
                <div>
                    <p class="text-xs font-semibold text-foreground">Habilitar inmediatamente</p>
                    <p class="text-2xs text-muted-foreground">Si está activo, se evaluará automáticamente en nuevos pedidos.</p>
                </div>
                <input
                    type="checkbox"
                    bind:checked={form.is_active}
                    class="h-5 w-5 rounded border-border text-primary focus:ring-primary cursor-pointer"
                />
            </div>

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
