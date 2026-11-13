<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Switch } from '@/components/ui/switch';
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

    const form = useForm({
        branch_id: null as number | null,
        name: '',
        code: '',
        address: '',
        is_active: true,
        is_default: false,
    });

    import { untrack } from 'svelte';

    let wasOpen = $state(false);

    $effect(() => {
        if (open && !wasOpen) {
            wasOpen = true;
            untrack(() => {
                if (mode === 'edit' && warehouse) {
                    form.branch_id = warehouse.branch_id || null;
                    form.name = warehouse.name ?? '';
                    form.code = warehouse.code ?? '';
                    form.address = warehouse.address || '';
                    form.is_active = Boolean(warehouse.is_active);
                    form.is_default = Boolean(warehouse.is_default);
                    form.clearErrors();
                } else if (mode === 'create') {
                    form.branch_id = branches.length > 0 ? branches[0].id : null;
                    form.name = '';
                    form.code = '';
                    form.address = '';
                    form.is_active = true;
                    form.is_default = false;
                    form.clearErrors();
                }
            });
        } else if (!open && wasOpen) {
            wasOpen = false;
        }
    });


    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();

        if (mode === 'edit' && warehouse) {
            form.put(`/tenant/configuracion/depositos/${warehouse.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    open = false;
                },
            });
        } else {
            form.post('/tenant/configuracion/depositos', {
                preserveScroll: true,
                onSuccess: () => {
                    open = false;
                },
            });
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="sm:max-w-[500px]">
        <form onsubmit={handleSubmit} class="flex flex-col gap-[1px]">
            <DialogHeader class="pb-3 border-b border-border/60 shrink-0">
                <div class="flex items-center gap-2.5 sm:gap-3.5">
                    <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                        <Warehouse class="size-5 sm:size-6 text-primary" />
                    </div>
                    <div class="min-w-0 flex-1 flex items-center">
                        <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                            {mode === 'create' ? 'Nuevo Depósito' : 'Editar Depósito'}
                        </DialogTitle>
                    </div>
                </div>
            </DialogHeader>

            <DialogBody class="p-5 sm:p-6 space-y-4">
                <!-- Nombre del Depósito -->
                <div class="space-y-1.5">
                    <Label for="wh-name" class="text-sm font-medium text-foreground">
                        Nombre del Depósito <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="wh-name"
                        bind:value={form.name}
                        placeholder="Ej: Depósito Central, Depósito Mayorista..."
                        required
                        class="h-10 text-sm font-medium rounded-xl"
                    />
                    {#if form.errors.name}
                        <p class="text-xs font-medium text-destructive">{form.errors.name}</p>
                    {/if}
                </div>

                <!-- Código Identificador -->
                <div class="space-y-1.5">
                    <Label for="wh-code" class="text-sm font-medium text-foreground">
                        Código / Identificador <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="wh-code"
                        bind:value={form.code}
                        placeholder="Ej: DEP-01, DEP-NORTE"
                        required
                        class="h-10 text-sm font-sans tabular-nums uppercase rounded-xl"
                    />
                    {#if form.errors.code}
                        <p class="text-xs font-medium text-destructive">{form.errors.code}</p>
                    {/if}
                </div>

                <!-- Sucursal Vinculada -->
                {#if branches.length > 0}
                    <div class="space-y-1.5">
                        <Label for="wh-branch" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                            <Building2 class="size-3.5 text-muted-foreground" />
                            <span>Sucursal Vinculada</span>
                        </Label>
                        <select
                            id="wh-branch"
                            bind:value={form.branch_id}
                            class="w-full h-10 rounded-xl border border-input bg-field px-3 text-sm font-medium text-foreground focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                        >
                            <option value={null}>Sin sucursal asignada (Depósito Independiente)</option>
                            {#each branches as br}
                                <option value={br.id}>
                                    {br.name} (Punto de Venta {br.point_of_sale})
                                </option>
                            {/each}
                        </select>
                        {#if form.errors.branch_id}
                            <p class="text-xs font-medium text-destructive">{form.errors.branch_id}</p>
                        {/if}
                    </div>
                {/if}

                <!-- Dirección Física -->
                <div class="space-y-1.5">
                    <Label for="wh-address" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <MapPin class="size-3.5 text-muted-foreground" />
                        <span>Dirección / Ubicación Física</span>
                    </Label>
                    <Input
                        id="wh-address"
                        bind:value={form.address}
                        placeholder="Ej: Calle Principal 1234, Galpón 4"
                        maxlength={255}
                        class="h-10 text-sm rounded-xl"
                    />
                    {#if form.errors.address}
                        <p class="text-xs font-medium text-destructive">{form.errors.address}</p>
                    {/if}
                </div>

                <!-- Switches con Separador Vertical y Centrados -->
                <div class="rounded-2xl border border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 p-3.5 sm:p-4 mt-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-around gap-4">
                        <!-- Switch 1: Activo -->
                        <div class="flex items-center justify-between sm:justify-center gap-3">
                            <div class="flex flex-col">
                                <Label for="wh-switch-active" class="text-sm font-medium cursor-pointer text-foreground">
                                    Depósito activo
                                </Label>
                                <span class="text-xs text-muted-foreground">Operativo en sistema</span>
                            </div>
                            <Switch
                                id="wh-switch-active"
                                bind:checked={form.is_active}
                            />
                        </div>

                        <!-- Separador vertical en desktop / horizontal en mobile -->
                        <div class="hidden sm:block w-px h-9 bg-border/80 self-center"></div>
                        <div class="block sm:hidden h-px w-full bg-border/60"></div>

                        <!-- Switch 2: Principal -->
                        <div class="flex items-center justify-between sm:justify-center gap-3">
                            <div class="flex flex-col">
                                <Label for="wh-switch-default" class="text-sm font-medium cursor-pointer text-foreground">
                                    Depósito principal
                                </Label>
                                <span class="text-xs text-muted-foreground">Por defecto para stock</span>
                            </div>
                            <Switch
                                id="wh-switch-default"
                                bind:checked={form.is_default}
                            />
                        </div>
                    </div>
                </div>
            </DialogBody>

            <DialogFooter class="p-5 sm:p-6 border-t border-border/60 gap-2.5 sm:gap-2.5 bg-muted/20 dark:bg-secondary/20">
                <Button
                    type="button"
                    variant="secondary"
                    class="min-h-[44px] px-5 rounded-xl text-sm font-medium"
                    onclick={() => (open = false)}
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    disabled={form.processing}
                    class="min-h-[44px] px-6 rounded-xl text-sm font-medium shadow-xs min-w-[150px]"
                >
                    {form.processing ? 'Guardando...' : mode === 'create' ? 'Crear depósito' : 'Guardar cambios'}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>


