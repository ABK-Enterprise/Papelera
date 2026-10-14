<script lang="ts">
    import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { router } from '@inertiajs/svelte';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Percent from 'lucide-svelte/icons/percent';
    import Navigation from 'lucide-svelte/icons/navigation';

    export interface ShippingZoneItem {
        id: number;
        name: string;
        distance_km: number | string;
        surcharge_percentage: number | string;
        is_active: boolean;
        orders_count?: number;
    }

    interface Props {
        open: boolean;
        mode: 'create' | 'edit';
        zone?: ShippingZoneItem | null;
        onclose?: () => void;
    }

    let { open = $bindable(false), mode = 'create', zone = null, onclose }: Props = $props();

    let name = $state('');
    let distanceKm = $state<number | string>('');
    let surchargePercentage = $state<number | string>('');
    let isActive = $state(true);
    let isSubmitting = $state(false);

    let isConfirmAlertOpen = $state(false);

    $effect(() => {
        if (open) {
            if (mode === 'edit' && zone) {
                name = zone.name;
                distanceKm = zone.distance_km;
                surchargePercentage = zone.surcharge_percentage;
                isActive = Boolean(zone.is_active);
            } else {
                name = '';
                distanceKm = '';
                surchargePercentage = '';
                isActive = true;
            }
        }
    });

    let isValid = $derived(
        name.trim().length > 0 &&
        Number(distanceKm) >= 0 &&
        distanceKm !== '' &&
        Number(surchargePercentage) >= 0 &&
        Number(surchargePercentage) <= 100 &&
        surchargePercentage !== ''
    );

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        if (!isValid) return;
        isConfirmAlertOpen = true;
    }

    function handleConfirmedSubmit() {
        isSubmitting = true;
        const payload = {
            name: name.trim(),
            distance_km: Number(distanceKm),
            surcharge_percentage: Number(surchargePercentage),
            is_active: isActive,
        };

        if (mode === 'create') {
            router.post('/tenant/configuracion/zonas', payload, {
                onSuccess: () => {
                    isSubmitting = false;
                    open = false;
                    onclose?.();
                },
                onError: () => {
                    isSubmitting = false;
                },
            });
        } else if (zone) {
            router.put(`/tenant/configuracion/zonas/${zone.id}`, payload, {
                onSuccess: () => {
                    isSubmitting = false;
                    open = false;
                    onclose?.();
                },
                onError: () => {
                    isSubmitting = false;
                },
            });
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="w-[92vw] max-w-[480px] p-6 space-y-4 rounded-2xl bg-card border-border shadow-xl">
        <DialogHeader class="pb-3 border-b border-border/60 shrink-0 text-left">
            <div class="flex items-center gap-2.5 sm:gap-3.5">
                <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                    <MapPin class="size-5 sm:size-6 text-primary" />
                </div>
                <div class="min-w-0 flex-1 flex items-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                        {mode === 'create' ? 'Nueva Zona de Envío' : 'Editar Zona de Envío'}
                    </DialogTitle>
                </div>
            </div>
        </DialogHeader>

        <form onsubmit={handleSubmit} class="space-y-4">
            <div class="space-y-1.5">
                <Label for="zone-name" class="text-sm font-medium text-foreground">Nombre de la Zona</Label>
                <Input
                    id="zone-name"
                    type="text"
                    bind:value={name}
                    placeholder="Ej. Gran Centro, Zona Norte, Periferia"
                    class="h-11 rounded-xl bg-background border-border/80 text-sm font-medium"
                    required
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <Label for="zone-distance" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <Navigation class="size-3.5 text-muted-foreground" />
                        Distancia
                    </Label>
                    <div class="relative">
                        <Input
                            id="zone-distance"
                            type="number"
                            step="0.01"
                            min="0"
                            bind:value={distanceKm}
                            placeholder="Ej. 20"
                            class="h-11 rounded-xl bg-background border-border/80 pr-12 text-sm tabular-nums font-medium"
                            required
                        />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-muted-foreground">km</span>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="zone-surcharge" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <Percent class="size-3.5 text-muted-foreground" />
                        Recargo
                    </Label>
                    <div class="relative">
                        <Input
                            id="zone-surcharge"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            bind:value={surchargePercentage}
                            placeholder="Ej. 5.00"
                            class="h-11 rounded-xl bg-background border-border/80 pr-10 text-sm tabular-nums font-medium"
                            required
                        />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-muted-foreground">%</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between p-3 rounded-xl border border-border/60 bg-muted/20">
                <div class="space-y-0.5">
                    <p class="text-sm font-medium text-foreground">Estado Operativo</p>
                    <p class="text-xs text-muted-foreground">Habilitar esta zona para su selección en nuevos pedidos</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" bind:checked={isActive} class="sr-only peer" />
                    <div class="w-11 h-6 bg-muted peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                </label>
            </div>

            <DialogFooter class="pt-2 flex flex-col-reverse sm:flex-row gap-2.5">
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-[44px] px-5 rounded-xl text-sm font-medium border-border/70 hover:bg-muted/60 transition-colors"
                    onclick={onclose}
                    disabled={isSubmitting}
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    variant="default"
                    class="min-h-[44px] px-5 rounded-xl text-sm font-medium shadow-xs"
                    disabled={!isValid || isSubmitting}
                >
                    {isSubmitting ? 'Guardando...' : (mode === 'create' ? 'Guardar Zona' : 'Actualizar Cambios')}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>

<ConfirmActionModal
    bind:open={isConfirmAlertOpen}
    title={mode === 'create' ? '¿Confirmar creación de zona?' : '¿Confirmar actualización de zona?'}
    description={`Se guardará la zona "${name}" con ${distanceKm} km de distancia y un recargo de ${surchargePercentage}%.`}
    confirmText={mode === 'create' ? 'Crear Zona' : 'Guardar Cambios'}
    onconfirm={handleConfirmedSubmit}
/>
