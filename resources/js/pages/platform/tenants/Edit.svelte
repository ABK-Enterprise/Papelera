<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { Link, useForm } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Building2 from 'lucide-svelte/icons/building-2';

    import Check from 'lucide-svelte/icons/check';
    import Package from 'lucide-svelte/icons/package';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import { sanitizeIdentificationInput } from '@/lib/numericSanitizer';

    interface ModuleItem {
        id: number;
        name: string;
        code: string;
        description?: string | null;
    }

    interface TenantData {
        id: number;
        name: string;
        cuit_tax_id: string | null;
        is_active: boolean;
        modules: ModuleItem[];
    }

    let { tenant, modules = [] }: { tenant: TenantData; modules: ModuleItem[] } = $props();

    const form = useForm(() => ({
        name: tenant.name,
        cuit_tax_id: tenant.cuit_tax_id || '',
        is_active: Boolean(tenant.is_active),
        module_ids: tenant.modules ? tenant.modules.map((m) => m.id) : [],
    }));

    let showDeactivateModal = $state(false);
    let showActivateModal = $state(false);

    function toggleModule(id: number) {
        if (form.module_ids.includes(id)) {
            form.module_ids = form.module_ids.filter((mId) => mId !== id);
        } else {
            form.module_ids = [...form.module_ids, id];
        }
    }

    function confirmDeactivate() {
        form.is_active = false;
        showDeactivateModal = false;
    }

    function confirmActivate() {
        form.is_active = true;
        showActivateModal = false;
    }

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.put(`/platform/tenants/${tenant.id}`, {
            preserveScroll: true,
        });
    }
</script>

<AppHead title={`Editar ${tenant.name} - Plataforma`} />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-4xl mx-auto w-full">
    <!-- Header Escalonado -->
    <div class="flex flex-col gap-3">
        <div class="flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary shrink-0">
                <Building2 class="size-5" />
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">{tenant.name}</h1>
                    {#if form.is_active}
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                          <span class="size-1.5 rounded-full bg-emerald-500"></span>
                          Activo
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20">
                          <span class="size-1.5 rounded-full bg-rose-500"></span>
                          Inactivo
                        </span>
                    {/if}
                </div>
            </div>
        </div>
        <div class="flex justify-end w-full">
            <Link href="/platform/tenants">
                <Button variant="outline" class="min-h-[40px] px-4 rounded-xl text-sm font-medium gap-2">
                    <ArrowLeft class="size-4" />
                    <span>Volver al listado</span>
                </Button>
            </Link>
        </div>
    </div>


    <form onsubmit={submit} class="space-y-6">
        <!-- 1. Datos Generales -->
        <Card class="border-border/60 shadow-xs">
            <CardHeader class="pb-4">
                <CardTitle class="text-base font-bold text-foreground">Datos Generales</CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-1.5">
                    <Label for="name" class="text-sm font-medium text-foreground">Razón Social / Nombre Fantasía *</Label>
                    <Input
                        id="name"
                        bind:value={form.name}
                        placeholder="Ej. Papelera Central S.A."
                        required
                        class="text-sm shadow-2xs"
                    />
                    {#if form.errors.name}
                        <p class="text-xs text-rose-700 dark:text-rose-400 font-medium">{form.errors.name}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="cuit" class="text-sm font-medium text-foreground">CUIT / Identificación Fiscal</Label>
                    <Input
                        id="cuit"
                        bind:value={form.cuit_tax_id}
                        placeholder="Ej. 30-71234567-8"
                        maxlength={13}
                        oninput={(e) => sanitizeIdentificationInput(e, (val) => { form.cuit_tax_id = val; })}
                        class="text-sm shadow-2xs font-sans tabular-nums"
                    />
                    {#if form.errors.cuit_tax_id}
                        <p class="text-xs text-rose-700 dark:text-rose-400 font-medium">{form.errors.cuit_tax_id}</p>
                    {/if}
                </div>
            </CardContent>
        </Card>

        <!-- 2. Módulos Habilitados -->
        <Card class="border-border/60 shadow-xs">
            <CardHeader class="pb-4">
                <div class="flex items-center gap-2">
                    <Package class="size-4 text-primary" />
                    <CardTitle class="text-base font-bold text-foreground">Módulos Habilitados</CardTitle>
                </div>
                <CardDescription class="text-xs text-muted-foreground">
                    Haz clic en cada módulo para activarlo o desactivarlo. Los permisos se actualizarán en cascada.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-3 md:grid-cols-2">
                {#each modules as mod (mod.id)}
                    {@const isSelected = form.module_ids.includes(mod.id)}
                    <div
                        role="button"
                        tabindex="0"
                        onclick={() => toggleModule(mod.id)}
                        onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && toggleModule(mod.id)}
                        class="relative flex items-start gap-3 rounded-xl border p-4 transition-all cursor-pointer select-none {isSelected
                            ? 'border-primary bg-primary/5 text-foreground shadow-2xs ring-1 ring-primary/20'
                            : 'border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 hover:bg-muted/70 dark:hover:bg-secondary/55 text-muted-foreground'}"
                    >
                        <!-- Indicador Checkbox visual -->
                        <div class="mt-0.5 size-4 rounded-md border flex items-center justify-center transition-colors shrink-0 {isSelected ? 'bg-primary text-primary-foreground border-primary' : 'border-input bg-field'}">
                            {#if isSelected}
                                <Check class="size-3 stroke-[3]" />
                            {/if}
                        </div>

                        <div class="space-y-1 min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold {isSelected ? 'text-foreground' : 'text-muted-foreground'}">
                                    {mod.name}
                                </span>
                                {#if isSelected}
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-primary">
                                        <Check class="size-3" />
                                        Activo
                                    </span>
                                {/if}
                            </div>
                            <span class="text-xs {isSelected ? 'text-primary/80' : 'text-muted-foreground/70'} block">
                                Código: {mod.code}
                            </span>
                            {#if mod.description}
                                <p class="text-xs text-muted-foreground line-clamp-2 leading-relaxed">
                                    {mod.description}
                                </p>
                            {/if}
                        </div>
                    </div>
                {/each}

                {#if modules.length === 0}
                    <div class="col-span-full py-6 text-center text-xs italic text-muted-foreground">
                        No hay módulos registrados en la plataforma.
                    </div>
                {/if}
            </CardContent>
        </Card>

        <!-- Footer / Acciones Principales -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-2 border-t border-border/60">
            <!-- Botón de Estado a la Izquierda (Desactivar / Activar con Modal) -->
            <div>
                {#if form.is_active}
                    <Button
                        type="button"
                        variant="outline"
                        onclick={() => (showDeactivateModal = true)}
                        class="min-h-[38px] text-sm font-medium text-rose-700 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-300 hover:bg-rose-500/10 border-rose-500/30 gap-1.5"
                    >
                        <ShieldAlert class="size-3.5" />
                        <span>Desactivar comercio</span>
                    </Button>
                {:else}
                    <Button
                        type="button"
                        variant="outline"
                        onclick={() => (showActivateModal = true)}
                        class="min-h-[38px] text-sm font-medium text-emerald-700 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-300 hover:bg-emerald-500/10 border-emerald-500/30 gap-1.5"
                    >
                        <ShieldCheck class="size-3.5" />
                        <span>Activar comercio</span>
                    </Button>
                {/if}
            </div>

            <!-- Botones a la Derecha (Cancelar y Guardar Cambios) -->
            <div class="flex items-center justify-end gap-3">
                <Link href="/platform/tenants">
                    <Button type="button" variant="secondary" class="min-h-[38px] text-sm font-medium">
                        Cancelar
                    </Button>
                </Link>
                <Button
                    type="submit"
                    disabled={form.processing}
                    class="min-h-[38px] text-sm font-medium min-w-36"
                >
                    {form.processing ? 'Guardando...' : 'Guardar Cambios'}
                </Button>
            </div>
        </div>
    </form>
</div>

<!-- Modal de Precaución: Desactivar Comercio -->
<ConfirmActionModal
    bind:open={showDeactivateModal}
    title="Desactivar Comercio"
    description={`¿Estás seguro de que deseas desactivar al comercio "${form.name}"? Los usuarios y administradores de este comercio no podrán ingresar al sistema.`}
    confirmText="Sí, desactivar comercio"
    cancelText="Cancelar"
    variant="danger"
    onconfirm={confirmDeactivate}
/>

<!-- Modal: Activar Comercio -->
<ConfirmActionModal
    bind:open={showActivateModal}
    title="Activar Comercio"
    description={`¿Deseas activar al comercio "${form.name}"? Los usuarios asociados recuperarán el acceso al sistema.`}
    confirmText="Sí, activar comercio"
    cancelText="Cancelar"
    variant="primary"
    onconfirm={confirmActivate}
/>
