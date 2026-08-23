<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { useForm } from '@inertiajs/svelte';
    import Truck from 'lucide-svelte/icons/truck';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Check from 'lucide-svelte/icons/check';
    import X from 'lucide-svelte/icons/x';

    let {
        open = $bindable(false),
    }: {
        open?: boolean;
    } = $props();

    const form = useForm({
        name: '',
        contact_number: '',
        email: '',
        address: '',
        is_active: true,
    });

    function toggleActive() {
        form.is_active = !form.is_active;
    }

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.post('/tenant/suppliers', {
            onSuccess: () => {
                open = false;
                form.reset();
            },
        });
    }

    function handleClose() {
        open = false;
        form.reset();
        form.clearErrors();
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-xl max-h-[90vh] overflow-y-auto p-6 sm:p-8">
        <div class="flex items-center justify-between pb-4 border-b border-border">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Truck class="size-5" />
                </div>
                <div>
                    <DialogTitle class="text-xl font-bold text-foreground">Añadir Proveedor</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Registra un nuevo proveedor o contacto comercial para tu comercio.
                    </DialogDescription>
                </div>
            </div>
            <button
                type="button"
                onclick={handleClose}
                class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                aria-label="Cerrar"
            >
                <X class="size-4" />
            </button>
        </div>

        <form onsubmit={submit} class="space-y-4 pt-4">
            <!-- Nombre del proveedor -->
            <div class="space-y-1.5">
                <Label for="create-supplier-name" class="text-xs font-semibold">
                    Nombre o Razón Social <span class="text-destructive">*</span>
                </Label>
                <Input
                    id="create-supplier-name"
                    bind:value={form.name}
                    placeholder="ej. Distribuidora Papelera Mayorista S.A."
                    required
                    class="h-9 text-sm"
                />
                {#if form.errors.name}
                    <p class="text-xs text-destructive">{form.errors.name}</p>
                {/if}
            </div>

            <!-- Teléfono / Contacto y Correo -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div class="space-y-1.5">
                    <Label for="create-supplier-phone" class="text-xs font-semibold flex items-center gap-1.5">
                        <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                        Teléfono / Contacto
                    </Label>
                    <Input
                        id="create-supplier-phone"
                        bind:value={form.contact_number}
                        placeholder="ej. +54 11 4321-8765"
                        class="h-9 text-sm"
                    />
                    {#if form.errors.contact_number}
                        <p class="text-xs text-destructive">{form.errors.contact_number}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="create-supplier-email" class="text-xs font-semibold flex items-center gap-1.5">
                        <Mail class="h-3.5 w-3.5 text-muted-foreground" />
                        Correo Electrónico
                    </Label>
                    <Input
                        id="create-supplier-email"
                        type="email"
                        bind:value={form.email}
                        placeholder="ventas@proveedor.com"
                        class="h-9 text-sm"
                    />
                    {#if form.errors.email}
                        <p class="text-xs text-destructive">{form.errors.email}</p>
                    {/if}
                </div>
            </div>

            <!-- Dirección comercial -->
            <div class="space-y-1.5">
                <Label for="create-supplier-address" class="text-xs font-semibold flex items-center gap-1.5">
                    <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                    Dirección Comercial
                </Label>
                <Input
                    id="create-supplier-address"
                    bind:value={form.address}
                    placeholder="ej. Av. Corrientes 1234, CABA"
                    class="h-9 text-sm"
                />
                {#if form.errors.address}
                    <p class="text-xs text-destructive">{form.errors.address}</p>
                {/if}
            </div>

            <!-- Estado Activo Toggle -->
            <div class="pt-1">
                <div
                    role="button"
                    tabindex="0"
                    onclick={toggleActive}
                    onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && toggleActive()}
                    class="flex items-center gap-3 rounded-xl border p-3.5 cursor-pointer select-none transition-all {form.is_active
                        ? 'border-primary/60 bg-primary/5 shadow-2xs'
                        : 'border-border/60 bg-card hover:bg-muted/40'}"
                >
                    <div class="size-4 rounded-md border flex items-center justify-center transition-colors shrink-0 {form.is_active ? 'bg-primary text-primary-foreground border-primary' : 'border-border bg-background'}">
                        {#if form.is_active}
                            <Check class="size-3 stroke-[3]" />
                        {/if}
                    </div>
                    <div class="text-xs space-y-0.5 flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1">
                            <span class="font-semibold text-foreground">Proveedor Activo</span>
                            {#if form.is_active}
                                <span class="text-[10px] font-bold text-primary">Activo</span>
                            {:else}
                                <span class="text-[10px] font-bold text-muted-foreground">Inactivo</span>
                            {/if}
                        </div>
                        <span class="text-muted-foreground block text-[11px]">Disponible para selección en compras y pedidos.</span>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-4 border-t border-border">
                <Button type="button" variant="outline" onclick={handleClose} disabled={form.processing}>
                    Cancelar
                </Button>
                <Button type="submit" disabled={form.processing} class="min-w-32">
                    {form.processing ? 'Guardando...' : 'Añadir Proveedor'}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>
