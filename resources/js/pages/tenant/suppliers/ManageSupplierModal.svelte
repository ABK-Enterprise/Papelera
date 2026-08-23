<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router, useForm } from '@inertiajs/svelte';
    import Truck from 'lucide-svelte/icons/truck';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Trash2 from 'lucide-svelte/icons/trash-2';
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

    $effect(() => {
        if (supplier) {
            form.name = supplier.name ?? '';
            form.contact_number = supplier.contact_number ?? '';
            form.email = supplier.email ?? '';
            form.address = supplier.address ?? '';
            form.is_active = supplier.is_active ?? true;
            form.clearErrors();
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

    function handleDelete() {
        if (!supplier) return;

        if (confirm(`¿Estás seguro de que deseas eliminar permanentemente al proveedor "${supplier.name}"?`)) {
            isDeleting = true;
            router.delete(`/tenant/suppliers/${supplier.id}`, {
                onFinish: () => {
                    isDeleting = false;
                    open = false;
                },
            });
        }
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
                    <DialogTitle class="text-xl font-bold text-foreground">Gestión de Proveedor</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground flex items-center gap-2 mt-0.5">
                        <span>Editar información comercial y estado del proveedor</span>
                        {#if supplier?.created_at}
                            <span>•</span>
                            <span class="inline-flex items-center gap-1">
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
                        class="h-9 text-sm"
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

            <!-- Estado Activo Toggle -->
            <div class="pt-1">
                <label class="flex items-center gap-2.5 rounded-lg border p-3 cursor-pointer transition-colors hover:bg-muted/40 {form.is_active ? 'border-primary/50 bg-primary/5' : 'border-border'}">
                    <Checkbox
                        checked={form.is_active}
                        onCheckedChange={(checked) => (form.is_active = !!checked)}
                    />
                    <div class="text-xs space-y-0.5">
                        <span class="font-semibold text-foreground block">Proveedor Activo</span>
                        <span class="text-muted-foreground block text-[11px]">Habilitado para compras y operaciones del comercio.</span>
                    </div>
                </label>
            </div>

            <!-- Footer: Izquierda Eliminar, Derecha Guardar y Cancelar -->
            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-5 border-t border-border mt-6">
                <!-- Abajo a la izquierda: Botón 'Eliminar proveedor' -->
                <Button
                    type="button"
                    variant="outline"
                    class="border-destructive/30 text-destructive hover:bg-destructive hover:text-destructive-foreground gap-1.5 justify-center"
                    onclick={handleDelete}
                    disabled={isDeleting || form.processing}
                >
                    <Trash2 class="h-4 w-4" />
                    {isDeleting ? 'Eliminando...' : 'Eliminar proveedor'}
                </Button>

                <!-- Abajo a la derecha: Botón 'Guardar cambios' y botón 'Cancelar' -->
                <div class="flex items-center justify-end gap-2">
                    <Button type="button" variant="outline" onclick={handleClose} disabled={form.processing || isDeleting}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={form.processing || isDeleting} class="min-w-36 font-semibold">
                        {form.processing ? 'Guardando...' : 'Guardar cambios'}
                    </Button>
                </div>
            </div>
        </form>
    </DialogContent>
</Dialog>
