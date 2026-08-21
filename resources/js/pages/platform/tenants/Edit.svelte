<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Link, useForm } from '@inertiajs/svelte';

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
        is_active: tenant.is_active,
        module_ids: tenant.modules.map((m) => m.id),
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
        form.put(`/platform/tenants/${tenant.id}`);
    }
</script>

<AppHead title={`Editar ${tenant.name} - Plataforma`} />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-4xl mx-auto w-full">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Configurar Comercio: {tenant.name}</h1>
                <p class="text-sm text-muted-foreground">
                    Modifica los datos generales y actualiza los módulos de suscripción contratados.
                </p>
            </div>
            <Link href="/platform/tenants">
                <Button variant="outline">Volver</Button>
            </Link>
        </div>

        <form onsubmit={submit} class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Datos Generales</CardTitle>
                    <CardDescription>Información fiscal y comercial.</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="name">Razón Social / Nombre Fantasía *</Label>
                        <Input
                            id="name"
                            bind:value={form.name}
                            required
                        />
                        {#if form.errors.name}
                            <p class="text-xs text-destructive">{form.errors.name}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="cuit">CUIT / Identificación Fiscal</Label>
                        <Input
                            id="cuit"
                            bind:value={form.cuit_tax_id}
                        />
                        {#if form.errors.cuit_tax_id}
                            <p class="text-xs text-destructive">{form.errors.cuit_tax_id}</p>
                        {/if}
                    </div>

                    <div class="col-span-full pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <Checkbox
                                checked={form.is_active}
                                onCheckedChange={(checked) => (form.is_active = !!checked)}
                            />
                            <span class="text-sm font-medium">Comercio Activo en el Sistema</span>
                        </label>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Módulos Habilitados</CardTitle>
                    <CardDescription>
                        Selecciona o deselecciona los módulos que este comercio puede utilizar.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    {#each modules as mod (mod.id)}
                        <label
                            class="flex items-start gap-3 rounded-lg border p-3.5 transition-colors cursor-pointer hover:bg-accent/50 {form.module_ids.includes(mod.id) ? 'border-primary bg-primary/5' : ''}"
                        >
                            <Checkbox
                                checked={form.module_ids.includes(mod.id)}
                                onCheckedChange={(checked) => toggleModule(mod.id, !!checked)}
                                class="mt-0.5"
                            />
                            <div class="space-y-1">
                                <span class="text-sm font-semibold leading-none">{mod.name}</span>
                                <span class="font-mono text-xs text-muted-foreground block">({mod.code})</span>
                                {#if mod.description}
                                    <p class="text-xs text-muted-foreground">{mod.description}</p>
                                {/if}
                            </div>
                        </label>
                    {/each}
                </CardContent>
            </Card>

            <div class="flex justify-end gap-3 pt-2">
                <Link href="/platform/tenants">
                    <Button type="button" variant="outline">Cancelar</Button>
                </Link>
                <Button type="submit" disabled={form.processing} class="min-w-36">
                    {form.processing ? 'Guardando...' : 'Guardar Cambios'}
                </Button>
            </div>
        </form>
    </div>
