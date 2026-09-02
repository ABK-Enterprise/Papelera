<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { useForm } from '@inertiajs/svelte';
    import Building2 from 'lucide-svelte/icons/building-2';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Warehouse from 'lucide-svelte/icons/warehouse';

    interface BranchOption {
        id: number;
        name: string;
        point_of_sale: number;
    }

    interface WarehouseItem {
        id: number;
        branch_id?: number | null;
        name: string;
        code: string;
        address?: string | null;
        is_active: boolean;
        is_default: boolean;
    }

    interface Props {
        open: boolean;
        mode?: 'create' | 'edit';
        warehouse?: WarehouseItem | null;
        branches: BranchOption[];
    }

    let { open = $bindable(false), mode = 'create', warehouse = null, branches = [] }: Props = $props();

    const form = useForm(() => ({
        branch_id: warehouse?.branch_id || (branches.length > 0 ? branches[0].id : null),
        name: warehouse?.name || '',
        code: warehouse?.code || '',
        address: warehouse?.address || '',
        is_active: warehouse?.is_active ?? true,
        is_default: warehouse?.is_default ?? false,
    }));

    $effect(() => {
        if (open) {
            if (mode === 'edit' && warehouse) {
                form.branch_id = warehouse.branch_id || null;
                form.name = warehouse.name;
                form.code = warehouse.code;
                form.address = warehouse.address || '';
                form.is_active = warehouse.is_active;
                form.is_default = warehouse.is_default;
            } else {
                form.branch_id = branches.length > 0 ? branches[0].id : null;
                form.name = '';
                form.code = '';
                form.address = '';
                form.is_active = true;
                form.is_default = false;
            }
            form.clearErrors();
        }
    });

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();

        if (mode === 'edit' && warehouse) {
            form.put(route('tenant.warehouses.update', warehouse.id), {
                preserveScroll: true,
                onSuccess: () => {
                    open = false;
                },
            });
        } else {
            form.post(route('tenant.warehouses.store'), {
                preserveScroll: true,
                onSuccess: () => {
                    open = false;
                },
            });
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="sm:max-w-[480px]">
        <form onsubmit={handleSubmit} class="space-y-4">
            <DialogHeader>
                <div class="flex items-center gap-2">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Warehouse class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-lg font-bold">
                            {mode === 'create' ? 'Nuevo Depósito' : 'Editar Depósito'}
                        </DialogTitle>
                        <DialogDescription class="text-xs">
                            {mode === 'create'
                                ? 'Define un nuevo espacio físico o depósito para control de inventario.'
                                : 'Modifica los parámetros y ubicación del depósito seleccionado.'}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-3.5 py-2">
                <!-- Nombre del Depósito -->
                <div class="space-y-1.5">
                    <Label for="wh-name" class="text-xs font-semibold">
                        Nombre del Depósito <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="wh-name"
                        bind:value={$form.name}
                        placeholder="Ej: Depósito Central, Depósito Mayorista..."
                        required
                        class="h-9 text-sm font-medium"
                    />
                    {#if $form.errors.name}
                        <p class="text-[11px] font-medium text-destructive">{$form.errors.name}</p>
                    {/if}
                </div>

                <!-- Código Identificador -->
                <div class="space-y-1.5">
                    <Label for="wh-code" class="text-xs font-semibold">
                        Código / Identificador <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="wh-code"
                        bind:value={$form.code}
                        placeholder="Ej: DEP-01, DEP-NORTE"
                        required
                        class="h-9 text-sm font-mono uppercase"
                    />
                    {#if $form.errors.code}
                        <p class="text-[11px] font-medium text-destructive">{$form.errors.code}</p>
                    {/if}
                </div>

                <!-- Sucursal Vinculada -->
                {#if branches.length > 0}
                    <div class="space-y-1.5">
                        <Label for="wh-branch" class="text-xs font-semibold">
                            Sucursal Vinculada
                        </Label>
                        <div class="relative">
                            <select
                                id="wh-branch"
                                bind:value={$form.branch_id}
                                class="w-full h-9 rounded-lg border border-input bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                            >
                                <option value={null}>Sin sucursal asignada (Depósito Independiente)</option>
                                {#each branches as br}
                                    <option value={br.id}>
                                        {br.name} (Punto de Venta {br.point_of_sale})
                                    </option>
                                {/each}
                            </select>
                        </div>
                        {#if $form.errors.branch_id}
                            <p class="text-[11px] font-medium text-destructive">{$form.errors.branch_id}</p>
                        {/if}
                    </div>
                {/if}

                <!-- Dirección Física -->
                <div class="space-y-1.5">
                    <Label for="wh-address" class="text-xs font-semibold">Dirección / Ubicación Física</Label>
                    <Input
                        id="wh-address"
                        bind:value={$form.address}
                        placeholder="Ej: Calle Principal 1234, Galpón 4"
                        class="h-9 text-xs"
                    />
                    {#if $form.errors.address}
                        <p class="text-[11px] font-medium text-destructive">{$form.errors.address}</p>
                    {/if}
                </div>

                <!-- Opciones / Estado -->
                <div class="pt-2 border-t border-border/50 flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-medium">
                        <input
                            type="checkbox"
                            bind:checked={$form.is_active}
                            class="size-4 rounded border-input text-primary focus:ring-primary"
                        />
                        <span>Depósito activo</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer text-xs font-medium">
                        <input
                            type="checkbox"
                            bind:checked={$form.is_default}
                            class="size-4 rounded border-input text-primary focus:ring-primary"
                        />
                        <span>Depósito principal por defecto</span>
                    </label>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-0">
                <Button
                    type="button"
                    variant="outline"
                    class="text-xs font-medium"
                    onclick={() => (open = false)}
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    disabled={$form.processing}
                    class="text-xs font-semibold shadow-xs"
                >
                    {$form.processing ? 'Guardando...' : mode === 'create' ? 'Crear depósito' : 'Guardar cambios'}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>
