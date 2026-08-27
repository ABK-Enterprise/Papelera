<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Link } from '@inertiajs/svelte';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Users from 'lucide-svelte/icons/users';
    import Package from 'lucide-svelte/icons/package';
    import Boxes from 'lucide-svelte/icons/boxes';
    import Plus from 'lucide-svelte/icons/plus';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import Activity from 'lucide-svelte/icons/activity';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import XCircle from 'lucide-svelte/icons/x-circle';

    interface PlatformStats {
        total_tenants: number;
        active_tenants: number;
        inactive_tenants: number;
        total_users: number;
        total_products: number;
        total_modules: number;
    }

    interface RecentTenant {
        id: number;
        name: string;
        cuit_tax_id: string | null;
        is_active: boolean;
        users_count: number;
        products_count: number;
        created_at?: string;
    }

    interface PlatformModule {
        id: number;
        code: string;
        name: string;
        is_active: boolean;
        tenants_count: number;
    }

    let {
        stats = {
            total_tenants: 0,
            active_tenants: 0,
            inactive_tenants: 0,
            total_users: 0,
            total_products: 0,
            total_modules: 0,
        },
        recentTenants = [],
        modules = [],
    }: {
        stats: PlatformStats;
        recentTenants: RecentTenant[];
        modules: PlatformModule[];
    } = $props();

    function formatDate(dateStr?: string) {
        if (!dateStr) return '-';
        try {
            return new Intl.DateTimeFormat('es-AR', {
                dateStyle: 'medium',
            }).format(new Date(dateStr));
        } catch {
            return dateStr;
        }
    }
</script>

<AppHead title="Panel de Superadministrador - Plataforma" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold tracking-wide bg-primary/10 text-primary border border-primary/20 flex items-center gap-1">
                    <ShieldCheck class="size-3.5" />
                    Plataforma global
                </span>
                <span class="text-xs text-muted-foreground">Sistema Multi-Tenant</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight leading-tight text-foreground mt-1">
                Panel de control de plataforma
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5 leading-relaxed">
                Métricas globales del ecosistema, estado de comercios y cobertura de módulos.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <Link href="/platform/tenants/create">
                <Button class="min-h-[40px] gap-2 shadow-sm font-medium rounded-xl">
                    <Plus class="size-4" />
                    <span>Crear comercio</span>
                </Button>
            </Link>
            <Link href="/platform/modules">
                <Button variant="outline" class="min-h-[40px] gap-2 font-medium rounded-xl border-border/70">
                    <Boxes class="size-4" />
                    <span>Gestionar módulos</span>
                </Button>
            </Link>
        </div>
    </div>

    <!-- Bento Grid de KPIs Globales -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Comercios -->
        <Card class="p-5 rounded-2xl border border-border/70 shadow-sm relative overflow-hidden bg-card">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Comercios</span>
                <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center border border-primary/20">
                    <Building2 class="size-5" />
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-bold tracking-tight tabular-nums text-foreground">{stats.total_tenants}</div>
                <div class="flex items-center gap-2 mt-2 text-xs">
                    <span class="inline-flex items-center gap-1 font-medium tracking-wide tabular-nums text-emerald-600 dark:text-emerald-400">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        {stats.active_tenants} activos
                    </span>
                    {#if stats.inactive_tenants > 0}
                        <span class="inline-flex items-center gap-1 font-medium tracking-wide tabular-nums text-rose-600 dark:text-rose-400">
                            <span class="size-1.5 rounded-full bg-rose-500"></span>
                            {stats.inactive_tenants} pausados
                        </span>
                    {/if}
                </div>
            </div>
        </Card>

        <!-- Card 2: Usuarios Globales -->
        <Card class="p-5 rounded-2xl border border-border/70 shadow-sm relative overflow-hidden bg-card">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Usuarios totales</span>
                <div class="size-10 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center border border-blue-500/20">
                    <Users class="size-5" />
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-bold tracking-tight tabular-nums text-foreground">{stats.total_users}</div>
                <p class="text-xs text-muted-foreground mt-2">Operadores y administradores registrados</p>
            </div>
        </Card>

        <!-- Card 3: Catálogo Global -->
        <Card class="p-5 rounded-2xl border border-border/70 shadow-sm relative overflow-hidden bg-card">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Catálogo global</span>
                <div class="size-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center border border-amber-500/20">
                    <Package class="size-5" />
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-bold tracking-tight tabular-nums text-foreground">{stats.total_products}</div>
                <p class="text-xs text-muted-foreground mt-2">Productos cargados en el sistema</p>
            </div>
        </Card>

        <!-- Card 4: Módulos del Sistema -->
        <Card class="p-5 rounded-2xl border border-border/70 shadow-sm relative overflow-hidden bg-card">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Módulos SaaS</span>
                <div class="size-10 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center border border-indigo-500/20">
                    <Boxes class="size-5" />
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-bold tracking-tight tabular-nums text-foreground">{stats.total_modules}</div>
                <p class="text-xs text-muted-foreground mt-2">Servicios modulares configurables</p>
            </div>
        </Card>
    </div>

    <!-- Sección 2 Columnas: Comercios Recientes & Cobertura de Módulos -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Comercios Recientes (2 Cols) -->
        <div class="lg:col-span-2 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base sm:text-lg font-semibold tracking-tight text-foreground">Comercios recientes</h2>
                    <p class="text-xs text-muted-foreground leading-relaxed">Últimos comercios incorporados a la plataforma</p>
                </div>
                <Link href="/platform/tenants" class="text-xs font-medium text-primary hover:underline flex items-center gap-1">
                    <span>Ver todos los comercios</span>
                    <ArrowUpRight class="size-3.5" />
                </Link>
            </div>

            <div class="flex flex-col gap-2.5">
                {#each recentTenants as tenant (tenant.id)}
                    <div class="bento-card p-4 rounded-xl border border-border/60 bg-card flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs hover:border-primary/40 transition-colors">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="size-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0 border border-primary/20">
                                <Building2 class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-sm text-foreground truncate">{tenant.name}</h3>
                                    {#if tenant.is_active}
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium tracking-wide bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/50">
                                            <span class="size-1 rounded-full bg-emerald-500"></span>
                                            Activo
                                        </span>
                                    {:else}
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium tracking-wide bg-muted text-muted-foreground border border-border">
                                            <span class="size-1 rounded-full bg-muted-foreground"></span>
                                            Pausado
                                        </span>
                                    {/if}
                                </div>
                                <p class="text-xs text-muted-foreground font-mono tabular-nums">
                                    CUIT: {tenant.cuit_tax_id || 'Sin CUIT'} • Alta: {formatDate(tenant.created_at)}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 text-xs font-mono shrink-0">
                            <div class="text-right">
                                <span class="text-muted-foreground block text-xs uppercase tracking-wider font-medium font-sans">Usuarios</span>
                                <span class="font-semibold tabular-nums text-foreground">{tenant.users_count}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-muted-foreground block text-xs uppercase tracking-wider font-medium font-sans">Productos</span>
                                <span class="font-semibold tabular-nums text-foreground">{tenant.products_count}</span>
                            </div>
                            <Link href={`/platform/tenants/${tenant.id}/edit`}>
                                <Button size="sm" variant="ghost" class="h-8 text-xs text-primary gap-1 font-medium">
                                    Editar
                                </Button>
                            </Link>
                        </div>
                    </div>
                {/each}

                {#if recentTenants.length === 0}
                    <div class="rounded-xl border border-dashed p-8 text-center bg-card/40 text-muted-foreground text-xs">
                        No hay comercios registrados actualmente.
                    </div>
                {/if}
            </div>
        </div>

        <!-- Cobertura de Módulos (1 Col) -->
        <div class="space-y-3">
            <div>
                <h2 class="text-base sm:text-lg font-semibold tracking-tight text-foreground">Adopción de módulos</h2>
                <p class="text-xs text-muted-foreground leading-relaxed">Comercios con módulos habilitados</p>
            </div>

            <Card class="p-4 rounded-xl border border-border/70 shadow-2xs divide-y divide-border/50 bg-card">
                {#each modules as mod (mod.id)}
                    <div class="py-3 first:pt-0 last:pb-0 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-sm text-foreground">{mod.name}</span>
                                {#if mod.is_active}
                                    <span class="size-1.5 rounded-full bg-emerald-500" title="Módulo activo globalmente"></span>
                                {:else}
                                    <span class="size-1.5 rounded-full bg-muted-foreground" title="Módulo desactivado"></span>
                                {/if}
                            </div>
                            <span class="text-xs text-muted-foreground font-mono tabular-nums">Código: {mod.code}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-medium tracking-wide tabular-nums text-primary bg-primary/10 px-2.5 py-0.5 rounded-full border border-primary/20">
                                {mod.tenants_count} {mod.tenants_count === 1 ? 'comercio' : 'comercios'}
                            </span>
                        </div>
                    </div>
                {/each}

                {#if modules.length === 0}
                    <p class="text-xs text-muted-foreground py-4 text-center">No hay módulos configurados.</p>
                {/if}
            </Card>
        </div>
    </div>
</div>
