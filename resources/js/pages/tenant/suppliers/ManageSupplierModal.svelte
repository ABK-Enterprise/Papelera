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
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { router, useForm } from '@inertiajs/svelte';
    import Truck from 'lucide-svelte/icons/truck';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import MoreHorizontal from 'lucide-svelte/icons/more-horizontal';
    import X from 'lucide-svelte/icons/x';
    import Calendar from 'lucide-svelte/icons/calendar';
    import IconContainer from '@/components/common/IconContainer.svelte';

    export interface SupplierItem {
        id: number;
        tenant_id: number;
        name: string;
        contact_number: string | null;
        email: string | null;
        address: string | null;
        is_active: boolean;
        created_at?: string;
        updated_at?: string;
    }

    let {
        open = $bindable(false),
        supplier = null,
    }: {
        open?: boolean;
        supplier?: SupplierItem | null;
    } = $props();

    const form = useForm({
        name: '',
        contact_number: '',
        email: '',
        address: '',
        is_active: true,
    });

    let isDeleting = $state(false);
    let lastLoadedSupplierId = $state<number | null>(null);

    // Modales de Confirmación
    let showDeactivateConfirm = $state(false);
    let showActivateConfirm = $state(false);
    let showDeleteConfirm = $state(false);

    $effect(() => {
        if (supplier && supplier.id !== lastLoadedSupplierId) {
            lastLoadedSupplierId = supplier.id;
            form.name = supplier.name ?? '';
            form.contact_number = supplier.contact_number ?? '';
            form.email = supplier.email ?? '';
            form.address = supplier.address ?? '';
            form.is_active = Boolean(supplier.is_active);
            form.clearErrors();
        } else if (!supplier) {
            lastLoadedSupplierId = null;
        }
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();
        if (!supplier) return;

        form.put(`/tenant/suppliers/${supplier.id}`, {
            onSuccess: () => {
                open = false;
            },
        });
    }

    function confirmToggleDeactivate() {
        form.is_active = false;
        showDeactivateConfirm = false;
    }

    function confirmToggleActivate() {
        form.is_active = true;
        showActivateConfirm = false;
    }

    function executeDelete() {
        if (!supplier) return;

        isDeleting = true;
        router.delete(`/tenant/suppliers/${supplier.id}`, {
            onFinish: () => {
                isDeleting = false;
                showDeleteConfirm = false;
                open = false;
            },
        });
    }

    function handleClose() {
        open = false;
        form.clearErrors();
    }

    function formatDate(dateStr?: string) {
        if (!dateStr) return '';
        try {
            const d = new Date(dateStr);
            return new Intl.DateTimeFormat('es-AR', {
                dateStyle: 'medium',
            }).format(d);
        } catch {
            return dateStr;
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-2xl max-h-[85vh]">
        <DialogHeader>
            <div class="flex items-center gap-3 min-w-0">
                <IconContainer size="md" variant="primary" icon="solar:delivery-bold-duotone" />
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <DialogTitle class="text-xl font-bold text-foreground">Gestión de Proveedor</DialogTitle>
                        {#if form.is_active}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                Activo
                            </span>
                        {:else}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                Inactivo
                            </span>
                        {/if}
                    </div>
                    {#if supplier?.created_at}
                        <DialogDescription class="text-xs text-muted-foreground flex items-center gap-1.5 mt-0.5">
                            <Calendar class="size-3 shrink-0" />
                            <span class="tabular-nums">{formatDate(supplier.created_at)}</span>
                        </DialogDescription>
                    {/if}
                </div>
            </div>

            <div class="flex items-center gap-1 shrink-0 -mr-1">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        {#snippet children(props)}
                            <button
                                {...props}
                                type="button"
                                class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer"
                                aria-label="Más opciones"
                            >
                                <MoreHorizontal class="size-4" />
                            </button>
                        {/snippet}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48 shadow-lg">
                        {#if form.is_active}
                            <DropdownMenuItem asChild>
                                {#snippet children(props)}
                                    <button
                                        type="button"
                                        class="{props.class} w-full flex items-center text-rose-600 dark:text-rose-400 focus:text-rose-600 focus:bg-rose-50 dark:focus:bg-rose-950/30 gap-2 text-xs font-medium cursor-pointer"
                                        onclick={() => {
                                            props.onClick?.();
                                            showDeactivateConfirm = true;
                                        }}
                                    >
                                        <ShieldAlert class="size-3.5 shrink-0" />
                                        <span>Desactivar proveedor</span>
                                    </button>
                                {/snippet}
                            </DropdownMenuItem>
                        {:else}
                            <DropdownMenuItem asChild>
                                {#snippet children(props)}
                                    <button
                                        type="button"
                                        class="{props.class} w-full flex items-center text-emerald-600 dark:text-emerald-400 focus:text-emerald-600 focus:bg-emerald-50 dark:focus:bg-emerald-950/30 gap-2 text-xs font-medium cursor-pointer"
                                        onclick={() => {
                                            props.onClick?.();
                                            showActivateConfirm = true;
                                        }}
                                    >
                                        <ShieldCheck class="size-3.5 shrink-0" />
                                        <span>Activar proveedor</span>
                                    </button>
                                {/snippet}
                            </DropdownMenuItem>
                        {/if}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            {#snippet children(props)}
                                <button
                                    type="button"
                                    class="{props.class} w-full flex items-center text-destructive focus:text-destructive focus:bg-destructive/10 gap-2 text-xs font-medium cursor-pointer"
                                    onclick={() => {
                                        props.onClick?.();
                                        showDeleteConfirm = true;
                                    }}
                                >
                                    <Trash2 class="size-3.5 shrink-0" />
                                    <span>Eliminar proveedor</span>
                                </button>
                            {/snippet}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <button
                    type="button"
                    onclick={handleClose}
                    class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer"
                    aria-label="Cerrar"
                >
                    <X class="size-4" />
                </button>
            </div>
        </DialogHeader>

        <DialogBody>
            <form id="manage-supplier-form" onsubmit={submit} class="space-y-4">
                <!-- Nombre o Razón Social -->
                <div class="space-y-1.5">
                    <Label for="manage-supplier-name" class="text-xs font-semibold">
                        Nombre o Razón Social <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="manage-supplier-name"
                        bind:value={form.name}
                        placeholder="Nombre del proveedor"
                        required
                        class="h-9 text-sm font-medium"
                    />
                    {#if form.errors.name}
                        <p class="text-xs text-destructive">{form.errors.name}</p>
                    {/if}
                </div>

                <!-- Teléfono y Email -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="space-y-1.5">
                        <Label for="manage-supplier-phone" class="text-xs font-semibold flex items-center gap-1.5">
                            <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                            Teléfono / Contacto
                        </Label>
                        <Input
                            id="manage-supplier-phone"
                            bind:value={form.contact_number}
                            placeholder="ej. +54 11 4321-8765"
                            class="h-9 text-sm font-sans tabular-nums"
                        />
                        {#if form.errors.contact_number}
                            <p class="text-xs text-destructive">{form.errors.contact_number}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="manage-supplier-email" class="text-xs font-semibold flex items-center gap-1.5">
                            <Mail class="h-3.5 w-3.5 text-muted-foreground" />
                            Correo Electrónico
                        </Label>
                        <Input
                            id="manage-supplier-email"
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

                <!-- Dirección -->
                <div class="space-y-1.5">
                    <Label for="manage-supplier-address" class="text-xs font-semibold flex items-center gap-1.5">
                        <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                        Dirección Comercial
                    </Label>
                    <Input
                        id="manage-supplier-address"
                        bind:value={form.address}
                        placeholder="ej. Av. Corrientes 1234, CABA"
                        class="h-9 text-sm"
                    />
                    {#if form.errors.address}
                        <p class="text-xs text-destructive">{form.errors.address}</p>
                    {/if}
                </div>
            </form>
        </DialogBody>

        <DialogFooter class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2">
            <Button type="button" variant="outline" size="sm" onclick={handleClose} disabled={form.processing || isDeleting} class="text-xs">
                Cancelar
            </Button>
            <Button type="submit" form="manage-supplier-form" size="sm" disabled={form.processing || isDeleting} class="min-w-32 text-xs font-semibold">
                {form.processing ? 'Guardando...' : 'Guardar cambios'}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal Precaución: Desactivar Proveedor -->
<ConfirmActionModal
    bind:open={showDeactivateConfirm}
    title="¿Desactivar proveedor?"
    description={`Al desactivar "${form.name}", no aparecerá disponible para emitir nuevas órdenes de compra.`}
    confirmText="Desactivar proveedor"
    variant="warning"
    onConfirm={confirmToggleDeactivate}
/>

<!-- Modal Precaución: Activar Proveedor -->
<ConfirmActionModal
    bind:open={showActivateConfirm}
    title="¿Activar proveedor?"
    description={`Al activar "${form.name}", volverá a estar disponible para emitir órdenes de compra.`}
    confirmText="Activar proveedor"
    variant="primary"
    onConfirm={confirmToggleActivate}
/>

<!-- Modal Alerta: Eliminar Proveedor -->
<ConfirmActionModal
    bind:open={showDeleteConfirm}
    title="¿Eliminar proveedor?"
    description={`Esta acción es irreversible. Se eliminará "${form.name}" del directorio comercial.`}
    confirmText="Eliminar proveedor"
    variant="destructive"
    isLoading={isDeleting}
    onConfirm={executeDelete}
/>
