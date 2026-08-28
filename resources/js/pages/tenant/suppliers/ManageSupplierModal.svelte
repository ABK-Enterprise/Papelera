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
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { router, useForm } from '@inertiajs/svelte';
    import Truck from 'lucide-svelte/icons/truck';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import X from 'lucide-svelte/icons/x';
    import Calendar from 'lucide-svelte/icons/calendar';

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
    <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto p-6 sm:p-8">
        <div class="flex items-center justify-between pb-4 border-b border-border">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Truck class="size-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <DialogTitle class="text-xl font-bold tracking-tight text-foreground">Gestionar proveedor</DialogTitle>
                        {#if form.is_active}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium tracking-wide bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                Activo
                            </span>
                        {:else}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium tracking-wide bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                Inactivo
                            </span>
                        {/if}
                    </div>
                    <DialogDescription class="text-xs text-muted-foreground flex items-center gap-2 mt-0.5">
                        <span>Editar información comercial y estado del proveedor</span>
                        {#if supplier?.created_at}
                            <span>•</span>
                            <span class="inline-flex items-center gap-1 tabular-nums">
                                <Calendar class="size-3" />
                                Registrado el {formatDate(supplier.created_at)}
                            </span>
                        {/if}
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
                <Label for="manage-supplier-name" class="text-sm font-medium text-foreground">
                    Nombre o Razón Social <span class="text-destructive">*</span>
                </Label>
                <Input
                    id="manage-supplier-name"
                    bind:value={form.name}
                    placeholder="Nombre del proveedor"
                    required
                    class="h-9 text-sm font-normal"
                />
                {#if form.errors.name}
                    <p class="text-xs text-destructive">{form.errors.name}</p>
                {/if}
            </div>

            <!-- Teléfono y Email -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div class="space-y-1.5">
                    <Label for="manage-supplier-phone" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                        Teléfono / Contacto
                    </Label>
                    <Input
                        id="manage-supplier-phone"
                        bind:value={form.contact_number}
                        placeholder="ej. +54 11 4321-8765"
                        class="h-9 text-sm tabular-nums"
                    />
                    {#if form.errors.contact_number}
                        <p class="text-xs text-destructive">{form.errors.contact_number}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="manage-supplier-email" class="text-sm font-medium text-foreground flex items-center gap-1.5">
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
                <Label for="manage-supplier-address" class="text-sm font-medium text-foreground flex items-center gap-1.5">
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

            <!-- Footer: Izquierda Desactivar/Eliminar, Derecha Guardar y Cancelar -->
            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-5 border-t border-border mt-6">
                <!-- Abajo a la izquierda: Botón de Estado y Eliminar -->
                <div class="flex items-center gap-2">
                    {#if form.is_active}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="text-xs font-medium text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/30 border-rose-200 dark:border-rose-900/40 gap-1.5"
                            onclick={() => (showDeactivateConfirm = true)}
                            disabled={form.processing || isDeleting}
                        >
                            <ShieldAlert class="size-3.5" />
                            <span>Desactivar proveedor</span>
                        </Button>
                    {:else}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="text-xs font-medium text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 border-emerald-200 dark:border-emerald-900/40 gap-1.5"
                            onclick={() => (showActivateConfirm = true)}
                            disabled={form.processing || isDeleting}
                        >
                            <ShieldCheck class="size-3.5" />
                            <span>Activar proveedor</span>
                        </Button>
                    {/if}

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-xs font-medium text-muted-foreground hover:text-destructive gap-1 px-2"
                        onclick={() => (showDeleteConfirm = true)}
                        disabled={isDeleting || form.processing}
                    >
                        <Trash2 class="size-3.5" />
                        <span>Eliminar</span>
                    </Button>
                </div>

                <!-- Abajo a la derecha: Botón 'Guardar cambios' y botón 'Cancelar' -->
                <div class="flex items-center justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" onclick={handleClose} disabled={form.processing || isDeleting} class="text-xs font-medium">
                        Cancelar
                    </Button>
                    <Button type="submit" size="sm" disabled={form.processing || isDeleting} class="min-w-32 text-xs font-medium">
                        {form.processing ? 'Guardando...' : 'Guardar cambios'}
                    </Button>
                </div>
            </div>
        </form>
    </DialogContent>
</Dialog>

<!-- Modal: Desactivar Proveedor -->
<ConfirmActionModal
    bind:open={showDeactivateConfirm}
    title="¿Desactivar proveedor?"
    description={`¿Estás seguro de que deseas desactivar al proveedor "${form.name || supplier?.name}"? No estará disponible para nuevas compras ni órdenes hasta que sea reactivado.`}
    confirmText="Desactivar proveedor"
    cancelText="Cancelar"
    variant="danger"
    onconfirm={confirmToggleDeactivate}
/>

<!-- Modal: Activar Proveedor -->
<ConfirmActionModal
    bind:open={showActivateConfirm}
    title="¿Activar proveedor?"
    description={`¿Deseas activar nuevamente al proveedor "${form.name || supplier?.name}" para habilitar compras y operaciones?`}
    confirmText="Activar proveedor"
    cancelText="Cancelar"
    variant="primary"
    onconfirm={confirmToggleActivate}
/>

<!-- Modal: Eliminar Proveedor Permanentemente -->
<ConfirmActionModal
    bind:open={showDeleteConfirm}
    title="¿Eliminar proveedor?"
    description={`¿Estás seguro de que deseas eliminar permanentemente al proveedor "${form.name || supplier?.name}"? Esta acción no se puede deshacer.`}
    confirmText="Eliminar proveedor"
    cancelText="Cancelar"
    variant="danger"
    loading={isDeleting}
    onconfirm={executeDelete}
/>
