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
    import { useForm } from '@inertiajs/svelte';
    import Truck from 'lucide-svelte/icons/truck';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Check from 'lucide-svelte/icons/check';
    import X from 'lucide-svelte/icons/x';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import { sanitizeIdentificationInput } from '@/lib/numericSanitizer';

    let {
        open = $bindable(false),
    }: {
        open?: boolean;
    } = $props();

    const form = useForm({
        name: '',
        tax_id: '',
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
        form.post('/tenant/proveedores', {
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
    <DialogContent class="max-w-xl max-h-[85vh]">
        <DialogHeader>
            <div class="flex items-center gap-3 min-w-0">
                <IconContainer size="md" variant="primary" icon="solar:delivery-bold-duotone" />
                <div class="min-w-0">
                    <DialogTitle class="text-xl font-bold text-foreground">Crear Proveedor</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                        Registra un nuevo proveedor o contacto comercial para tu comercio.
                    </DialogDescription>
                </div>
            </div>
            <button
                type="button"
                onclick={handleClose}
                class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer shrink-0"
                aria-label="Cerrar"
            >
                <X class="size-4" />
            </button>
        </DialogHeader>


        <DialogBody>
            <form id="create-supplier-form" onsubmit={submit} class="space-y-4">
                <!-- Nombre y CUIT del proveedor -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="space-y-1.5">
                        <Label for="create-supplier-name" class="text-sm font-medium text-foreground">
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

                    <div class="space-y-1.5">
                        <Label for="create-supplier-tax-id" class="text-sm font-medium text-foreground">
                            CUIT
                        </Label>
                        <Input
                            id="create-supplier-tax-id"
                            bind:value={form.tax_id}
                            placeholder="ej. 30-71234567-9"
                            maxlength="13"
                            oninput={(e) => sanitizeIdentificationInput(e, (val) => { form.tax_id = val; })}
                            class="h-9 text-sm tabular-nums"
                        />
                        {#if form.errors.tax_id}
                            <p class="text-xs text-destructive">{form.errors.tax_id}</p>
                        {/if}
                    </div>
                </div>

                <!-- Teléfono / Contacto y Correo -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="space-y-1.5">
                        <Label for="create-supplier-phone" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                            <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                            Teléfono / Contacto
                        </Label>
                        <Input
                            id="create-supplier-phone"
                            bind:value={form.contact_number}
                            placeholder="ej. +54 11 4321-8765"
                            class="h-9 text-sm tabular-nums"
                        />
                        {#if form.errors.contact_number}
                            <p class="text-xs text-destructive">{form.errors.contact_number}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="create-supplier-email" class="text-sm font-medium text-foreground flex items-center gap-1.5">
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
                    <Label for="create-supplier-address" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                        Dirección Comercial
                    </Label>
                    <Input
                        id="create-supplier-address"
                        bind:value={form.address}
                        maxlength="255"
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
                                    <span class="text-xs font-bold text-primary">Activo</span>
                                {:else}
                                    <span class="text-xs font-bold text-muted-foreground">Inactivo</span>
                                {/if}
                            </div>
                            <span class="text-muted-foreground block text-xs">Disponible para selección en compras y pedidos.</span>
                        </div>
                    </div>
                </div>
            </form>
        </DialogBody>

        <DialogFooter>
            <Button type="button" variant="outline" onclick={handleClose} disabled={form.processing} class="min-h-[44px] px-4 rounded-xl text-sm font-medium">
                Cancelar
            </Button>
            <Button type="submit" form="create-supplier-form" disabled={form.processing} class="min-h-[44px] px-4 rounded-xl text-sm font-medium min-w-32">
                {form.processing ? 'Creando...' : 'Crear proveedor'}
            </Button>
        </DialogFooter>

    </DialogContent>
</Dialog>
