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
    import { useForm } from '@inertiajs/svelte';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Package from 'lucide-svelte/icons/package';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';

    interface AvailableModule {
        id: number;
        name: string;
        code: string;
        description: string | null;
    }

    let {
        open = $bindable(false),
        modules = [],
    }: {
        open?: boolean;
        modules?: AvailableModule[];
    } = $props();

    const form = useForm({
        tenant: {
            name: '',
            cuit_tax_id: '',
        },
        admin: {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
        },
        module_ids: [] as number[],
    });

    function toggleModule(id: number, checked: boolean) {
        if (checked) {
            if (!form.module_ids.includes(id)) {
                form.module_ids = [...form.module_ids, id];
            }
        } else {
            form.module_ids = form.module_ids.filter((mId) => mId !== id);
        }
    }

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.post('/platform/tenants', {
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
                    <Building2 class="size-5" />
                </div>
                <div>
                    <DialogTitle class="text-xl font-bold text-foreground">Añadir Nuevo Comercio</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Registra el comercio, su administrador principal y los módulos activos.
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

        <form onsubmit={submit} class="space-y-6 pt-4">
            <!-- Sección 1: Comercio -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <Building2 class="size-4 text-primary" />
                    <span>1. Datos del Comercio</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 bg-muted/20 p-3.5 rounded-lg border border-border/50">
                    <div class="space-y-1.5">
                        <Label for="modal-tenant-name" class="text-xs font-medium">Nombre o Razón Social *</Label>
                        <Input
                            id="modal-tenant-name"
                            bind:value={form.tenant.name}
                            placeholder="ej. Papelera Central S.A."
                            required
                            class="h-9 text-sm"
                        />
                        {#if form.errors['tenant.name']}
                            <p class="text-xs text-destructive">{form.errors['tenant.name']}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="modal-tenant-cuit" class="text-xs font-medium">CUIT / Identificación Fiscal</Label>
                        <Input
                            id="modal-tenant-cuit"
                            bind:value={form.tenant.cuit_tax_id}
                            placeholder="ej. 30-71234567-8"
                            class="h-9 text-sm"
                        />
                        {#if form.errors['tenant.cuit_tax_id']}
                            <p class="text-xs text-destructive">{form.errors['tenant.cuit_tax_id']}</p>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Sección 2: Administrador -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <User class="size-4 text-primary" />
                    <span>2. Administrador Inicial</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 bg-muted/20 p-3.5 rounded-lg border border-border/50">
                    <div class="space-y-1.5">
                        <Label for="modal-admin-name" class="text-xs font-medium">Nombre y Apellido *</Label>
                        <Input
                            id="modal-admin-name"
                            bind:value={form.admin.name}
                            placeholder="ej. Carlos Rodríguez"
                            required
                            class="h-9 text-sm"
                        />
                        {#if form.errors['admin.name']}
                            <p class="text-xs text-destructive">{form.errors['admin.name']}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="modal-admin-email" class="text-xs font-medium">Email de Acceso *</Label>
                        <Input
                            id="modal-admin-email"
                            type="email"
                            bind:value={form.admin.email}
                            placeholder="admin@comercio.com"
                            required
                            class="h-9 text-sm"
                        />
                        {#if form.errors['admin.email']}
                            <p class="text-xs text-destructive">{form.errors['admin.email']}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="modal-admin-password" class="text-xs font-medium">Contraseña Inicial *</Label>
                        <Input
                            id="modal-admin-password"
                            type="password"
                            bind:value={form.admin.password}
                            placeholder="Mínimo 8 caracteres"
                            required
                            class="h-9 text-sm"
                        />
                        {#if form.errors['admin.password']}
                            <p class="text-xs text-destructive">{form.errors['admin.password']}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="modal-admin-password-confirm" class="text-xs font-medium">Confirmar Contraseña *</Label>
                        <Input
                            id="modal-admin-password-confirm"
                            type="password"
                            bind:value={form.admin.password_confirmation}
                            placeholder="Repite la contraseña"
                            required
                            class="h-9 text-sm"
                        />
                    </div>
                </div>
            </div>

            <!-- Sección 3: Módulos Habilitados -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <Package class="size-4 text-primary" />
                    <span>3. Módulos Habilitados</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-48 overflow-y-auto pr-1">
                    {#each modules as mod (mod.id)}
                        <label
                            class="flex items-start gap-2.5 rounded-lg border p-2.5 transition-colors cursor-pointer hover:bg-muted/50 {form.module_ids.includes(mod.id) ? 'border-primary/60 bg-primary/5' : 'border-border/60'}"
                        >
                            <Checkbox
                                checked={form.module_ids.includes(mod.id)}
                                onCheckedChange={(checked) => toggleModule(mod.id, !!checked)}
                                class="mt-0.5"
                            />
                            <div class="space-y-0.5 min-w-0">
                                <span class="text-xs font-semibold text-foreground block truncate">{mod.name}</span>
                                <span class="font-mono text-[10px] text-muted-foreground block">({mod.code})</span>
                                {#if mod.description}
                                    <p class="text-[11px] text-muted-foreground line-clamp-1">{mod.description}</p>
                                {/if}
                            </div>
                        </label>
                    {/each}

                    {#if modules.length === 0}
                        <p class="col-span-full py-2 text-xs italic text-muted-foreground text-center">
                            No hay módulos activos en el catálogo. Puedes activarlos luego.
                        </p>
                    {/if}
                </div>
            </div>

            <DialogFooter class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-4 border-t border-border">
                <Button type="button" variant="outline" onclick={handleClose} disabled={form.processing}>
                    Cancelar
                </Button>
                <Button type="submit" disabled={form.processing} class="min-w-32">
                    {form.processing ? 'Creando...' : 'Crear Comercio'}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>
