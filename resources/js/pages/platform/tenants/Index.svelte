<script lang="ts">
    import AppLayout from '@/layouts/AppLayout.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import { Link, router } from '@inertiajs/svelte';

    interface ModuleSummary {
        id: number;
        name: string;
        code: string;
    }

    interface TenantItem {
        id: number;
        name: string;
        cuit_tax_id: string | null;
        is_active: boolean;
        users_count?: number;
        modules: ModuleSummary[];
        created_at: string;
    }

    let { tenants = [] }: { tenants: TenantItem[] } = $props();

    function toggleActive(tenant: TenantItem) {
        router.patch(`/platform/tenants/${tenant.id}/toggle-active`);
    }
</script>

<AppHead title="Gestión de Comercios - Plataforma" />

<AppLayout
    breadcrumbs={[
        { title: 'Plataforma', href: '/dashboard' },
        { title: 'Comercios', href: '/platform/tenants' },
    ]}
>

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-8">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Comercios Adheridos (Tenants)</h1>
                <p class="text-sm text-muted-foreground">
                    Administra las empresas clientes, sus administradores y los módulos contratados.
                </p>
            </div>
            <div class="flex gap-2">
                <Link href="/platform/modules">
                    <Button variant="outline">Gestionar Módulos</Button>
                </Link>
                <Link href="/platform/tenants/create">
                    <Button>+ Nuevo Comercio</Button>
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            {#each tenants as tenant (tenant.id)}
                <Card class="flex flex-col justify-between transition-all hover:shadow-md {tenant.is_active ? '' : 'opacity-60 bg-muted/30'}">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <CardTitle class="text-lg">{tenant.name}</CardTitle>
                                {#if tenant.cuit_tax_id}
                                    <span class="font-mono text-xs text-muted-foreground">CUIT: {tenant.cuit_tax_id}</span>
                                {/if}
                            </div>
                            <Badge variant={tenant.is_active ? 'default' : 'secondary'}>
                                {tenant.is_active ? 'Activo' : 'Inactivo'}
                            </Badge>
                        </div>
                        <CardDescription class="text-xs">
                            {tenant.users_count ?? 0} usuarios / empleados registrados
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-4 pt-0">
                        <div class="space-y-1.5">
                            <span class="text-xs font-semibold text-muted-foreground">Módulos Habilitados ({tenant.modules.length}):</span>
                            <div class="flex flex-wrap gap-1.5">
                                {#each tenant.modules as mod}
                                    <Badge variant="outline" class="text-xs">
                                        {mod.name}
                                    </Badge>
                                {/each}
                                {#if tenant.modules.length === 0}
                                    <span class="text-xs italic text-muted-foreground">Sin módulos asignados</span>
                                {/if}
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t pt-3 text-xs">
                            <Button
                                size="sm"
                                variant="outline"
                                onclick={() => toggleActive(tenant)}
                            >
                                {tenant.is_active ? 'Desactivar' : 'Activar'}
                            </Button>
                            <Link href={`/platform/tenants/${tenant.id}/edit`}>
                                <Button size="sm" variant="default">
                                    Editar / Módulos
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            {/each}

            {#if tenants.length === 0}
                <div class="col-span-full rounded-xl border border-dashed p-12 text-center">
                    <h3 class="text-lg font-medium">No hay comercios registrados aún</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Crea el primer comercio para asignarle su administrador y módulos de trabajo.
                    </p>
                    <Link href="/platform/tenants/create" class="mt-4 inline-block">
                        <Button>Crear Primer Comercio</Button>
                    </Link>
                </div>
            {/if}
        </div>
    </div>
</AppLayout>
