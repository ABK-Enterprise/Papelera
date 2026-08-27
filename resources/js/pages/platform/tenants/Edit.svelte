<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { Link, useForm } from '@inertiajs/svelte';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Check from 'lucide-svelte/icons/check';
    import Package from 'lucide-svelte/icons/package';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import { Spinner } from '@/components/ui/spinner';

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

    const form = useForm({
        name: tenant.name,
        cuit_tax_id: tenant.cuit_tax_id || '',
        is_active: Boolean(tenant.is_active),
        module_ids: tenant.modules ? tenant.modules.map((m) => m.id) : [],
    });

    let showDeactivateModal = $state(false);
    let showActivateModal = $state(false);

    function toggleModule(id: number) {
        if ($form.module_ids.includes(id)) {
            $form.module_ids = $form.module_ids.filter((mId) => mId !== id);
        } else {
            $form.module_ids = [...$form.module_ids, id];
        }
    }

    function confirmDeactivate() {
        $form.is_active = false;
        showDeactivateModal = false;
    }

    function confirmActivate() {
        $form.is_active = true;
        showActivateModal = false;
    }

    function submit(e: SubmitEvent) {
        e.preventDefault();
        $form.put(`/platform/tenants/${tenant.id}`, {
            preserveScroll: true,
        });
    }
</script>

<AppHead title={`Editar ${tenant.name} - Plataforma`} />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-4xl mx-auto w-full">
    <!-- Encabezado -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary shrink-0">
                <Building2 class="h-6 w-6 stroke-[1.75]" />
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">{tenant.name}</h1>
                    {#if $form.is_active}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Activo
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                            Inactivo
                        </span>
                    {/if}
                </div>
                <p class="text-sm text-muted-foreground mt-0.5">
                    Modifica los datos fiscales y actualiza los módulos de suscripción del comercio.
                </p>
            </div>
        </div>
        <Link href="/platform/tenants">
            <Button variant="outline" class="min-h-[44px] gap-2 rounded-xl border-border/70 font-medium">
                <ArrowLeft class="h-4 w-4" />
                <span>Volver</span>
            </Button>
        </Link>
    </div>

    <form onsubmit={submit} class="space-y-6">
        <!-- 1. Datos Generales -->
        <Card class="rounded-2xl border-border/70 bg-card shadow-xs">
            <CardHeader class="pb-4">
                <CardTitle class="text-base font-bold text-foreground">Datos Generales</CardTitle>
                <CardDescription class="text-xs text-muted-foreground">
                    Información fiscal y razón social del comercio.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2 pt-0">
                <div class="space-y-2">
                    <Label for="name" class="text-sm font-medium">Razón Social / Nombre Fantasía *</Label>
                    <Input
                        id="name"
                        bind:value={$form.name}
                        placeholder="Ej. Papelera Central S.A."
                        required
                        class="h-11 rounded-xl border-border/70 bg-muted/20 px-3.5 transition-colors focus:bg-background"
                    />
                    {#if $form.errors.name}
                        <p class="text-xs font-medium text-destructive">{$form.errors.name}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="cuit" class="text-sm font-medium">CUIT / Identificación Fiscal</Label>
                    <Input
                        id="cuit"
                        bind:value={$form.cuit_tax_id}
                        placeholder="Ej. 30-71234567-8"
                        class="h-11 rounded-xl border-border/70 bg-muted/20 px-3.5 transition-colors focus:bg-background font-mono"
                    />
                    {#if $form.errors.cuit_tax_id}
                        <p class="text-xs font-medium text-destructive">{$form.errors.cuit_tax_id}</p>
                    {/if}
                </div>
            </CardContent>
        </Card>

        <!-- 2. Módulos Habilitados -->
        <Card class="rounded-2xl border-border/70 bg-card shadow-xs">
            <CardHeader class="pb-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Package class="h-5 w-5 text-primary" />
                        <CardTitle class="text-base font-bold text-foreground">Módulos Habilitados</CardTitle>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                        {$form.module_ids.length} activos
                    </span>
                </div>
                <CardDescription class="text-xs text-muted-foreground mt-0.5">
                    Haz clic en cada módulo para activarlo o desactivarlo. Los permisos del rol administrador se actualizarán en cascada.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-3 md:grid-cols-2 pt-0">
                {#each modules as mod (mod.id)}
                    {@const isSelected = $form.module_ids.includes(mod.id)}
                    <button
                        type="button"
                        onclick={() => toggleModule(mod.id)}
                        class="relative flex min-h-[56px] items-start gap-3.5 rounded-2xl border p-4 text-left transition-all cursor-pointer select-none {isSelected
                            ? 'border-primary bg-primary/5 text-foreground shadow-2xs ring-1 ring-primary/20'
                            : 'border-border/60 bg-card hover:bg-muted/30 text-muted-foreground'}"
                    >
                        <!-- Indicador Checkbox visual -->
                        <div class="mt-0.5 size-4.5 rounded-md border flex items-center justify-center transition-colors shrink-0 {isSelected ? 'bg-primary text-primary-foreground border-primary' : 'border-border bg-background'}">
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
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-primary">
                                        <Check class="size-3" />
                                        Activo
                                    </span>
                                {/if}
                            </div>
                            <span class="font-mono text-[11px] {isSelected ? 'text-primary/80' : 'text-muted-foreground/70'} block">
                                Código: {mod.code}
                            </span>
                            {#if mod.description}
                                <p class="text-xs text-muted-foreground line-clamp-2 leading-relaxed">
                                    {mod.description}
                                </p>
                            {/if}
                        </div>
                    </button>
                {/each}

                {#if modules.length === 0}
                    <div class="col-span-full py-8 text-center text-xs italic text-muted-foreground">
                        No hay módulos registrados en la plataforma.
                    </div>
                {/if}
            </CardContent>
        </Card>

        <!-- Footer / Acciones Principales -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-2 border-t border-border/60">
            <!-- Botón de Estado a la Izquierda -->
            <div>
                {#if $form.is_active}
                    <Button
                        type="button"
                        variant="outline"
                        onclick={() => (showDeactivateModal = true)}
                        class="min-h-[44px] rounded-xl text-xs font-semibold text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/30 border-rose-200 dark:border-rose-900/40 gap-2"
                    >
                        <ShieldAlert class="h-4 w-4" />
                        <span>Desactivar comercio</span>
                    </Button>
                {:else}
                    <Button
                        type="button"
                        variant="outline"
                        onclick={() => (showActivateModal = true)}
                        class="min-h-[44px] rounded-xl text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 border-emerald-200 dark:border-emerald-900/40 gap-2"
                    >
                        <ShieldCheck class="h-4 w-4" />
                        <span>Activar comercio</span>
                    </Button>
                {/if}
            </div>

            <!-- Botones a la Derecha -->
            <div class="flex items-center justify-end gap-3">
                <Link href="/platform/tenants">
                    <Button type="button" variant="outline" class="min-h-[44px] px-5 rounded-xl font-medium border-border/70">
                        Cancelar
                    </Button>
                </Link>
                <Button
                    type="submit"
                    disabled={$form.processing}
                    class="min-h-[44px] min-w-36 gap-2 rounded-xl font-medium shadow-sm"
                >
                    {#if $form.processing}
                        <Spinner class="mr-2" />
                        <span>Guardando...</span>
                    {:else}
                        <Check class="h-4 w-4" />
                        <span>Guardar Cambios</span>
                    {/if}
                </Button>
            </div>
        </div>
    </form>
</div>

<!-- Modal de Precaución: Desactivar Comercio -->
<ConfirmActionModal
    bind:open={showDeactivateModal}
    title="¿Desactivar comercio?"
    description={`Esta acción revoca de inmediato el acceso al sistema para todos los usuarios y administradores de "${$form.name}".`}
    confirmText="Desactivar Comercio"
    cancelText="Cancelar"
    variant="danger"
    onconfirm={confirmDeactivate}
/>

<!-- Modal: Activar Comercio -->
<ConfirmActionModal
    bind:open={showActivateModal}
    title="¿Activar comercio?"
    description={`Al activar "${$form.name}", los usuarios y administradores recuperarán el acceso operativo al sistema.`}
    confirmText="Activar Comercio"
    cancelText="Cancelar"
    variant="primary"
    onconfirm={confirmActivate}
/>
