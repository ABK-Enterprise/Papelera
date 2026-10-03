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
    import { useForm } from '@inertiajs/svelte';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Check from 'lucide-svelte/icons/check';
    import Package from 'lucide-svelte/icons/package';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';
    import IconContainer from '@/components/common/IconContainer.svelte';

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
        if (form.module_ids.includes(id)) {
            form.module_ids = form.module_ids.filter((mId) => mId !== id);
        } else {
            form.module_ids = [...form.module_ids, id];
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
    <DialogContent class="max-w-2xl max-h-[85vh]">
        <DialogHeader>
            <div class="flex items-center gap-3 min-w-0">
                <IconContainer size="md" variant="primary" icon="solar:shop-bold-duotone" />
                <DialogTitle class="text-xl font-bold text-foreground">Crear Comercio</DialogTitle>
            </div>
            <button
                type="button"
                onclick={handleClose}
                class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors shrink-0 -mr-1"
                aria-label="Cerrar"
            >
                <X class="size-4" />
            </button>
        </DialogHeader>

        <DialogBody>
            <form id="create-tenant-form" onsubmit={submit} class="space-y-6">
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
                                class="h-9 text-sm font-medium"
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
                                class="h-9 text-sm font-sans tabular-nums"
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
                                class="h-9 text-sm font-medium"
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
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        {#each modules as mod (mod.id)}
                            {@const isSelected = form.module_ids.includes(mod.id)}
                            <div
                                role="button"
                                tabindex="0"
                                onclick={() => toggleModule(mod.id)}
                                onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && toggleModule(mod.id)}
                                class="flex items-start gap-2.5 rounded-lg border p-2.5 transition-colors cursor-pointer select-none {isSelected
                                    ? 'border-primary/60 bg-primary/5 shadow-2xs'
                                    : 'border-border/60 bg-card hover:bg-muted/50'}"
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
                                    {#if mod.description}
                                        <p class="text-[11px] text-muted-foreground line-clamp-1">{mod.description}</p>
                                    {/if}
                                </div>
                            </div>
                        {/each}

                        {#if modules.length === 0}
                            <p class="col-span-full py-2 text-xs italic text-muted-foreground text-center">
                                No hay módulos activos en el catálogo. Puedes activarlos luego.
                            </p>
                        {/if}
                    </div>
                </div>
            </form>
        </DialogBody>

        <DialogFooter>
            <Button type="button" variant="outline" size="sm" onclick={handleClose} disabled={form.processing} class="text-xs">
                Cancelar
            </Button>
            <Button type="submit" form="create-tenant-form" size="sm" disabled={form.processing} class="min-w-32 text-xs font-semibold">
                {form.processing ? 'Creando...' : 'Crear comercio'}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
