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
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { useForm } from '@inertiajs/svelte';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Check from 'lucide-svelte/icons/check';
    import Package from 'lucide-svelte/icons/package';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';
    import Store from 'lucide-svelte/icons/store';
    import { Spinner } from '@/components/ui/spinner';

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

    function toggleModule(id: number) {
        if ($form.module_ids.includes(id)) {
            $form.module_ids = $form.module_ids.filter((mId) => mId !== id);
        } else {
            $form.module_ids = [...$form.module_ids, id];
        }
    }

    function submit(e: SubmitEvent) {
        e.preventDefault();
        $form.post('/platform/tenants', {
            onSuccess: () => {
                open = false;
                $form.reset();
            },
        });
    }

    function handleClose() {
        open = false;
        $form.reset();
        $form.clearErrors();
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto p-6 sm:p-8 rounded-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-border/60">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary shrink-0">
                    <Building2 class="h-6 w-6 stroke-[1.75]" />
                </div>
                <div>
                    <DialogTitle class="text-xl font-bold text-foreground">Añadir Nuevo Comercio</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                        Registra el comercio, su administrador principal y los módulos contratados.
                    </DialogDescription>
                </div>
            </div>
            <button
                type="button"
                onclick={handleClose}
                class="rounded-xl p-2 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                aria-label="Cerrar"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <form onsubmit={submit} class="space-y-6 pt-4">
            <!-- Sección 1: Comercio -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <Store class="h-4 w-4 text-primary" />
                    <span>1. Datos del Comercio</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 bg-muted/20 p-4 rounded-xl border border-border/60">
                    <div class="space-y-1.5">
                        <Label for="modal-tenant-name" class="text-xs font-medium">Razón Social / Nombre *</Label>
                        <Input
                            id="modal-tenant-name"
                            bind:value={$form.tenant.name}
                            placeholder="ej. Papelera Central S.A."
                            required
                            class="h-10 rounded-xl border-border/70 text-sm"
                        />
                        {#if $form.errors['tenant.name']}
                            <p class="text-xs font-medium text-destructive">{$form.errors['tenant.name']}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="modal-tenant-cuit" class="text-xs font-medium">CUIT / Identificación Fiscal</Label>
                        <Input
                            id="modal-tenant-cuit"
                            bind:value={$form.tenant.cuit_tax_id}
                            placeholder="ej. 30-71234567-8"
                            class="h-10 rounded-xl border-border/70 text-sm font-mono"
                        />
                        {#if $form.errors['tenant.cuit_tax_id']}
                            <p class="text-xs font-medium text-destructive">{$form.errors['tenant.cuit_tax_id']}</p>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Sección 2: Administrador -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <User class="h-4 w-4 text-primary" />
                    <span>2. Administrador Principal</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 bg-muted/20 p-4 rounded-xl border border-border/60">
                    <div class="space-y-1.5">
                        <Label for="modal-admin-name" class="text-xs font-medium">Nombre y Apellido *</Label>
                        <Input
                            id="modal-admin-name"
                            bind:value={$form.admin.name}
                            placeholder="ej. Carlos Rodríguez"
                            required
                            class="h-10 rounded-xl border-border/70 text-sm"
                        />
                        {#if $form.errors['admin.name']}
                            <p class="text-xs font-medium text-destructive">{$form.errors['admin.name']}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="modal-admin-email" class="text-xs font-medium">Email de Acceso *</Label>
                        <Input
                            id="modal-admin-email"
                            type="email"
                            bind:value={$form.admin.email}
                            placeholder="admin@comercio.com"
                            required
                            class="h-10 rounded-xl border-border/70 text-sm"
                        />
                        {#if $form.errors['admin.email']}
                            <p class="text-xs font-medium text-destructive">{$form.errors['admin.email']}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="modal-admin-password" class="text-xs font-medium">Contraseña Inicial *</Label>
                        <PasswordInput
                            id="modal-admin-password"
                            bind:value={$form.admin.password}
                            placeholder="Mínimo 8 caracteres"
                            required
                            class="h-10 rounded-xl border-border/70 text-sm"
                        />
                        {#if $form.errors['admin.password']}
                            <p class="text-xs font-medium text-destructive">{$form.errors['admin.password']}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="modal-admin-password-confirm" class="text-xs font-medium">Confirmar Contraseña *</Label>
                        <PasswordInput
                            id="modal-admin-password-confirm"
                            bind:value={$form.admin.password_confirmation}
                            placeholder="Repite la contraseña"
                            required
                            class="h-10 rounded-xl border-border/70 text-sm"
                        />
                    </div>
                </div>
            </div>

            <!-- Sección 3: Módulos Habilitados -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                        <Package class="h-4 w-4 text-primary" />
                        <span>3. Módulos Habilitados</span>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                        {$form.module_ids.length} seleccionados
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-48 overflow-y-auto pr-1">
                    {#each modules as mod (mod.id)}
                        {@const isSelected = $form.module_ids.includes(mod.id)}
                        <button
                            type="button"
                            onclick={() => toggleModule(mod.id)}
                            class="flex min-h-[44px] items-start gap-2.5 rounded-xl border p-2.5 text-left transition-colors cursor-pointer select-none {isSelected
                                ? 'border-primary bg-primary/5 shadow-2xs'
                                : 'border-border/60 bg-card hover:bg-muted/40'}"
                        >
                            <div class="mt-0.5 size-4 rounded-md border flex items-center justify-center transition-colors shrink-0 {isSelected ? 'bg-primary text-primary-foreground border-primary' : 'border-border bg-background'}">
                                {#if isSelected}
                                    <Check class="size-3 stroke-[3]" />
                                {/if}
                            </div>
                            <div class="space-y-0.5 min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-xs font-semibold {isSelected ? 'text-foreground' : 'text-muted-foreground'} truncate">
                                        {mod.name}
                                    </span>
                                    {#if isSelected}
                                        <span class="inline-flex items-center text-[10px] font-bold text-primary shrink-0">
                                            <Check class="size-2.5 mr-0.5" />
                                            Activo
                                        </span>
                                    {/if}
                                </div>
                                <span class="font-mono text-[10px] text-muted-foreground block">({mod.code})</span>
                            </div>
                        </button>
                    {/each}

                    {#if modules.length === 0}
                        <p class="col-span-full py-3 text-xs italic text-muted-foreground text-center">
                            No hay módulos activos en el catálogo. Puedes activarlos luego.
                        </p>
                    {/if}
                </div>
            </div>

            <DialogFooter class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 pt-4 border-t border-border/60">
                <Button type="button" variant="outline" onclick={handleClose} disabled={$form.processing} class="min-h-[44px] px-5 rounded-xl font-medium border-border/70">
                    Cancelar
                </Button>
                <Button type="submit" disabled={$form.processing} class="min-h-[44px] min-w-36 gap-2 rounded-xl font-medium shadow-sm">
                    {#if $form.processing}
                        <Spinner class="mr-2" />
                        <span>Creando comercio...</span>
                    {:else}
                        <Building2 class="h-4 w-4" />
                        <span>Crear Comercio</span>
                    {/if}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>
