<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { useForm } from '@inertiajs/svelte';
    import UserPlus from 'lucide-svelte/icons/user-plus';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import FileText from 'lucide-svelte/icons/file-text';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import X from 'lucide-svelte/icons/x';

    let {
        open = $bindable(false),
    }: {
        open?: boolean;
    } = $props();

    const form = useForm({
        name: '',
        tax_id: '',
        tax_condition: 'consumidor_final',
        email: '',
        phone: '',
        address: '',
        credit_limit: 0,
        is_defaulter: false,
        is_active: true,
        notes: '',
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();

        form.post('/tenant/customers', {
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
    <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto p-6 sm:p-8">
        <div class="flex items-center justify-between pb-4 border-b border-border">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <UserPlus class="size-5" />
                </div>
                <div>
                    <DialogTitle class="text-xl font-bold tracking-tight text-foreground">Añadir cliente</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                        Registra un cliente en el directorio comercial para pedidos y facturación.
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
            <!-- Nombre o Razón Social -->
            <div class="space-y-1.5">
                <Label for="create-customer-name" class="text-sm font-medium text-foreground">
                    Nombre / Razón Social <span class="text-destructive">*</span>
                </Label>
                <Input
                    id="create-customer-name"
                    bind:value={form.name}
                    placeholder="ej. Librería San Martín / Juan Pérez"
                    required
                    class="h-9 text-sm font-normal"
                />
                {#if form.errors.name}
                    <p class="text-xs text-destructive">{form.errors.name}</p>
                {/if}
            </div>

            <!-- CUIT / DNI y Condición Fiscal -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div class="space-y-1.5">
                    <Label for="create-customer-tax-id" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <FileText class="h-3.5 w-3.5 text-muted-foreground" />
                        CUIT / CUIL / DNI
                    </Label>
                    <Input
                        id="create-customer-tax-id"
                        bind:value={form.tax_id}
                        placeholder="ej. 20-33445566-9 o 33445566"
                        class="h-9 text-sm font-mono tabular-nums"
                    />
                    {#if form.errors.tax_id}
                        <p class="text-xs text-destructive">{form.errors.tax_id}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="create-customer-tax-condition" class="text-sm font-medium text-foreground">
                        Condición Fiscal (IVA) <span class="text-destructive">*</span>
                    </Label>
                    <select
                        id="create-customer-tax-condition"
                        bind:value={form.tax_condition}
                        class="h-9 w-full rounded-lg border border-input bg-background px-3 py-1.5 text-sm font-normal text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="consumidor_final">Consumidor Final</option>
                        <option value="responsable_inscripto">Responsable Inscripto</option>
                        <option value="monotributo">Monotributo</option>
                        <option value="exento">Exento de IVA</option>
                    </select>
                    {#if form.errors.tax_condition}
                        <p class="text-xs text-destructive">{form.errors.tax_condition}</p>
                    {/if}
                </div>
            </div>

            <!-- Teléfono y Email -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div class="space-y-1.5">
                    <Label for="create-customer-phone" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                        Teléfono / WhatsApp
                    </Label>
                    <Input
                        id="create-customer-phone"
                        bind:value={form.phone}
                        placeholder="ej. +54 9 11 4455-6677"
                        class="h-9 text-sm font-mono tabular-nums"
                    />
                    {#if form.errors.phone}
                        <p class="text-xs text-destructive">{form.errors.phone}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="create-customer-email" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <Mail class="h-3.5 w-3.5 text-muted-foreground" />
                        Correo Electrónico
                    </Label>
                    <Input
                        id="create-customer-email"
                        type="email"
                        bind:value={form.email}
                        placeholder="contacto@cliente.com"
                        class="h-9 text-sm"
                    />
                    {#if form.errors.email}
                        <p class="text-xs text-destructive">{form.errors.email}</p>
                    {/if}
                </div>
            </div>

            <!-- Dirección Comercial / Entrega -->
            <div class="space-y-1.5">
                <Label for="create-customer-address" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                    <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                    Dirección Comercial / Entrega
                </Label>
                <Input
                    id="create-customer-address"
                    bind:value={form.address}
                    placeholder="ej. Av. Rivadavia 4520, Piso 3"
                    class="h-9 text-sm"
                />
                {#if form.errors.address}
                    <p class="text-xs text-destructive">{form.errors.address}</p>
                {/if}
            </div>

            <!-- Límite de Crédito & Flags -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 p-3.5 rounded-xl border bg-muted/15">
                <div class="space-y-1.5">
                    <Label for="create-customer-credit" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <CreditCard class="h-3.5 w-3.5 text-muted-foreground" />
                        Límite de Crédito ($ ARS)
                    </Label>
                    <Input
                        id="create-customer-credit"
                        type="number"
                        step="0.01"
                        min="0"
                        bind:value={form.credit_limit}
                        placeholder="0.00"
                        class="h-9 text-sm font-mono tabular-nums"
                    />
                </div>

                <div class="flex flex-col justify-center gap-2 pt-1">
                    <label class="flex items-center gap-2 text-xs font-medium text-foreground cursor-pointer">
                        <input
                            type="checkbox"
                            bind:checked={form.is_defaulter}
                            class="size-4 rounded border-border text-rose-600 focus:ring-rose-500"
                        />
                        <span class="flex items-center gap-1 text-rose-600 dark:text-rose-400">
                            <AlertTriangle class="size-3.5" />
                            Marcar con saldo deudor / moroso
                        </span>
                    </label>

                    <label class="flex items-center gap-2 text-xs font-medium text-muted-foreground cursor-pointer">
                        <input
                            type="checkbox"
                            bind:checked={form.is_active}
                            class="size-4 rounded border-border text-primary focus:ring-primary"
                        />
                        <span>Cliente activo</span>
                    </label>
                </div>
            </div>

            <!-- Observaciones / Notas -->
            <div class="space-y-1.5">
                <Label for="create-customer-notes" class="text-sm font-medium text-foreground">
                    Notas Internas / Preferencias de Entrega
                </Label>
                <textarea
                    id="create-customer-notes"
                    bind:value={form.notes}
                    rows="2"
                    placeholder="ej. Horarios de recepción, condiciones de pago convenidas..."
                    class="w-full rounded-lg border border-input bg-background p-2.5 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                ></textarea>
                {#if form.errors.notes}
                    <p class="text-xs text-destructive">{form.errors.notes}</p>
                {/if}
            </div>

            <!-- Footer: Botones -->
            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border mt-6">
                <Button type="button" variant="outline" size="sm" onclick={handleClose} disabled={form.processing} class="text-xs font-medium">
                    Cancelar
                </Button>
                <Button type="submit" size="sm" disabled={form.processing} class="min-w-32 text-xs font-medium">
                    {form.processing ? 'Guardando...' : 'Guardar cliente'}
                </Button>
            </div>
        </form>
    </DialogContent>
</Dialog>
