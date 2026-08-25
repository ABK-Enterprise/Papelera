<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Card } from '@/components/ui/card';
    import Boxes from 'lucide-svelte/icons/boxes';
    import Package from 'lucide-svelte/icons/package';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Truck from 'lucide-svelte/icons/truck';
    import UserCheck from 'lucide-svelte/icons/user-check';
    import FileText from 'lucide-svelte/icons/file-text';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import Lock from 'lucide-svelte/icons/lock';
    import Search from 'lucide-svelte/icons/search';
    import Building2 from 'lucide-svelte/icons/building-2';
    import KeyRound from 'lucide-svelte/icons/key-round';
    import Layers from 'lucide-svelte/icons/layers';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Sparkles from 'lucide-svelte/icons/sparkles';

    interface PermissionItem {
        id: number;
        name: string;
        module_id: number;
    }

    interface ModuleItem {
        id: number;
        name: string;
        code: string;
        description: string | null;
        is_active: boolean;
        tenants_count?: number;
        permissions: PermissionItem[];
    }

    let { modules = [] }: { modules: ModuleItem[] } = $props();

    let searchQuery = $state('');

    // Totales calculados
    let totalPermissionsCount = $derived(
        modules.reduce((acc, m) => acc + (m.permissions?.length || 0), 0)
    );

    let totalTenantsSubscribedCount = $derived(
        modules.reduce((acc, m) => acc + (m.tenants_count || 0), 0)
    );

    // Módulos filtrados por búsqueda
    let filteredModules = $derived.by(() => {
        const q = searchQuery.toLowerCase().trim();
        if (!q) return modules;

        return modules.filter((m) => {
            const matchName = m.name.toLowerCase().includes(q);
            const matchCode = m.code.toLowerCase().includes(q);
            const matchDesc = m.description?.toLowerCase().includes(q);
            const matchPerms = m.permissions.some((p) => p.name.toLowerCase().includes(q));
            return matchName || matchCode || matchDesc || matchPerms;
        });
    });

    function getModuleConfig(code: string) {
        switch (code) {
            case 'productos':
                return {
                    icon: Package,
                    colorClass: 'text-emerald-600 dark:text-emerald-400',
                    bgClass: 'bg-emerald-500/10 border-emerald-500/25',
                    badgeClass: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20',
                    accentColor: 'border-emerald-500/30',
                };
            case 'stock':
                return {
                    icon: Boxes,
                    colorClass: 'text-amber-600 dark:text-amber-400',
                    bgClass: 'bg-amber-500/10 border-amber-500/25',
                    badgeClass: 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20',
                    accentColor: 'border-amber-500/30',
                };
            case 'pedidos':
                return {
                    icon: ShoppingBag,
                    colorClass: 'text-blue-600 dark:text-blue-400',
                    bgClass: 'bg-blue-500/10 border-blue-500/25',
                    badgeClass: 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/20',
                    accentColor: 'border-blue-500/30',
                };
            case 'proveedores':
                return {
                    icon: Truck,
                    colorClass: 'text-violet-600 dark:text-violet-400',
                    bgClass: 'bg-violet-500/10 border-violet-500/25',
                    badgeClass: 'bg-violet-500/10 text-violet-700 dark:text-violet-300 border-violet-500/20',
                    accentColor: 'border-violet-500/30',
                };
            case 'clientes':
                return {
                    icon: UserCheck,
                    colorClass: 'text-teal-600 dark:text-teal-400',
                    bgClass: 'bg-teal-500/10 border-teal-500/25',
                    badgeClass: 'bg-teal-500/10 text-teal-700 dark:text-teal-300 border-teal-500/20',
                    accentColor: 'border-teal-500/30',
                };
            case 'facturacion_arca':
                return {
                    icon: FileText,
                    colorClass: 'text-rose-600 dark:text-rose-400',
                    bgClass: 'bg-rose-500/10 border-rose-500/25',
                    badgeClass: 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20',
                    accentColor: 'border-rose-500/30',
                };
            default:
                return {
                    icon: Layers,
                    colorClass: 'text-primary',
                    bgClass: 'bg-primary/10 border-primary/20',
                    badgeClass: 'bg-primary/10 text-primary border-primary/20',
                    accentColor: 'border-primary/20',
                };
        }
    }

    function getPermissionDotColor(permName: string) {
        if (permName.endsWith('.ver') || permName.endsWith('.consultar')) {
            return 'bg-sky-500';
        }
        if (permName.endsWith('.crear') || permName.endsWith('.ingresar')) {
            return 'bg-emerald-500';
        }
        if (permName.endsWith('.editar') || permName.endsWith('.editar_estado')) {
            return 'bg-amber-500';
        }
        if (permName.endsWith('.eliminar') || permName.endsWith('.anular') || permName.endsWith('.cancelar')) {
            return 'bg-rose-500';
        }
        return 'bg-primary';
    }
</script>

<AppHead title="Módulos y Permisos del Sistema - Plataforma" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Header Hero Minimalista -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary/10 text-primary border border-primary/20">
                    <ShieldCheck class="size-3.5" />
                    Arquitectura del Sistema
                </span>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-muted text-muted-foreground border border-border/60">
                    <Lock class="size-3" />
                    Estructura Inmutable
                </span>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                Módulos y Permisos
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5 max-w-2xl">
                Catálogo central de módulos funcionales y permisos granulares de la plataforma. Diseñados como bloques fijos que garantizan la integridad y el modelo Zero-Trust en cada comercio.
            </p>
        </div>
    </div>

    <!-- Bento KPI Summary Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- KPI 1: Módulos Base -->
        <Card class="rounded-xl p-4.5 border border-border/60 shadow-[0px_10px_30px_rgba(0,0,0,0.02)] flex items-center gap-4 bg-card/80 backdrop-blur-xs">
            <div class="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20 shrink-0">
                <Boxes class="size-6" />
            </div>
            <div>
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider block">Módulos del Sistema</span>
                <span class="text-2xl font-bold text-foreground font-mono">{modules.length} Módulos Activos</span>
            </div>
        </Card>

        <!-- KPI 2: Total Permisos Granulares -->
        <Card class="rounded-xl p-4.5 border border-border/60 shadow-[0px_10px_30px_rgba(0,0,0,0.02)] flex items-center gap-4 bg-card/80 backdrop-blur-xs">
            <div class="flex size-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 shrink-0">
                <KeyRound class="size-6" />
            </div>
            <div>
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider block">Permisos Granulares</span>
                <span class="text-2xl font-bold text-foreground font-mono">{totalPermissionsCount} Permisos RBAC</span>
            </div>
        </Card>

        <!-- KPI 3: Comercios Vinculados -->
        <Card class="rounded-xl p-4.5 border border-border/60 shadow-[0px_10px_30px_rgba(0,0,0,0.02)] flex items-center gap-4 bg-card/80 backdrop-blur-xs">
            <div class="flex size-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 shrink-0">
                <Building2 class="size-6" />
            </div>
            <div>
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider block">Adopción en Comercios</span>
                <span class="text-2xl font-bold text-foreground font-mono">{totalTenantsSubscribedCount} Asignaciones</span>
            </div>
        </Card>
    </div>

    <!-- Buscador Reactivo de Módulos y Permisos -->
    <Card class="rounded-xl p-3.5 shadow-[0px_10px_40px_rgba(0,0,0,0.02)] border border-border/60">
        <div class="relative w-full">
            <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
                type="text"
                bind:value={searchQuery}
                placeholder="Filtrar por nombre de módulo, código slug o permiso específico (ej. 'stock', 'facturas.emitir', 'clientes')..."
                class="h-10 w-full rounded-lg border border-input bg-muted/20 pl-10 pr-4 text-sm placeholder:text-muted-foreground focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary transition-all"
            />
        </div>
    </Card>

    <!-- Grid de Módulos (Bento Cards Minimalistas y Modernas) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {#each filteredModules as mod (mod.id)}
            {@const cfg = getModuleConfig(mod.code)}
            {@const ModuleIcon = cfg.icon}

            <div
                class="bento-card group flex flex-col justify-between rounded-2xl border border-border/70 bg-card p-5 shadow-[0px_10px_30px_rgba(0,0,0,0.02)] transition-all duration-200 hover:border-primary/40 hover:shadow-md hover:bg-muted/10 relative overflow-hidden"
            >
                <!-- Indicador de brillo sutil en hover -->
                <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full blur-2xl pointer-events-none group-hover:bg-primary/10 transition-colors"></div>

                <!-- Cabecera de la Card: Icono + Título + Badge de Sistema -->
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="flex size-13 shrink-0 items-center justify-center rounded-xl font-bold border {cfg.bgClass} transition-transform group-hover:scale-105">
                                <ModuleIcon class="size-6.5 {cfg.colorClass} stroke-[1.8]" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-base md:text-lg text-foreground group-hover:text-primary transition-colors truncate">
                                    {mod.name}
                                </h3>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="inline-flex items-center font-mono text-[11px] font-semibold text-muted-foreground bg-muted/80 px-2 py-0.5 rounded border border-border/50">
                                        {mod.code}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <span class="inline-flex items-center gap-1 shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/50">
                            <CheckCircle2 class="size-3" />
                            Activo
                        </span>
                    </div>

                    <!-- Descripción del Alcance -->
                    {#if mod.description}
                        <p class="text-xs text-muted-foreground leading-relaxed line-clamp-2">
                            {mod.description}
                        </p>
                    {/if}

                    <!-- Métricas Rápidas del Módulo -->
                    <div class="grid grid-cols-2 gap-2.5 py-1">
                        <div class="flex items-center gap-2 p-2 rounded-lg bg-muted/30 border border-border/40 text-xs">
                            <KeyRound class="size-3.5 text-muted-foreground" />
                            <span class="font-semibold text-foreground font-mono">{mod.permissions.length}</span>
                            <span class="text-muted-foreground text-[11px]">permisos</span>
                        </div>
                        <div class="flex items-center gap-2 p-2 rounded-lg bg-muted/30 border border-border/40 text-xs">
                            <Building2 class="size-3.5 text-muted-foreground" />
                            <span class="font-semibold text-foreground font-mono">{mod.tenants_count ?? 0}</span>
                            <span class="text-muted-foreground text-[11px]">comercios</span>
                        </div>
                    </div>

                    <!-- Lista de Permisos Granulares -->
                    <div class="space-y-2 pt-1">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1">
                                <KeyRound class="size-3 text-primary" />
                                Permisos Granulares
                            </span>
                            <span class="text-[10px] text-muted-foreground font-mono">
                                Total: {mod.permissions.length}
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-1.5 p-2.5 rounded-xl bg-muted/20 border border-border/60 max-h-40 overflow-y-auto">
                            {#each mod.permissions as perm (perm.id)}
                                {@const dotColor = getPermissionDotColor(perm.name)}
                                <span class="inline-flex items-center gap-1.5 font-mono text-[11px] font-medium bg-background px-2.5 py-1 rounded-md border border-border/60 text-foreground/90 shadow-2xs">
                                    <span class="size-1.5 rounded-full {dotColor}"></span>
                                    {perm.name}
                                </span>
                            {/each}

                            {#if mod.permissions.length === 0}
                                <span class="text-xs italic text-muted-foreground py-1">
                                    Sin permisos granulares asignados
                                </span>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- Footer de la Card: Badge de Integridad Fija -->
                <div class="flex items-center justify-between border-t border-border/50 pt-3.5 mt-4 text-[11px] text-muted-foreground">
                    <div class="flex items-center gap-1.5">
                        <ShieldCheck class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                        <span class="font-medium text-foreground/80">Estructura fija del sistema</span>
                    </div>
                    <span class="font-mono text-[10px] text-muted-foreground">Zero-Trust Enforced</span>
                </div>
            </div>
        {/each}

        {#if filteredModules.length === 0}
            <div class="col-span-full rounded-xl border border-dashed p-12 text-center bg-card/40">
                <Boxes class="mx-auto h-12 w-12 text-muted-foreground/40 mb-3" />
                <h3 class="font-semibold text-lg text-foreground">No se encontraron módulos</h3>
                <p class="text-sm text-muted-foreground mt-1 max-w-sm mx-auto">
                    No hay módulos ni permisos que coincidan con el término de búsqueda "{searchQuery}".
                </p>
            </div>
        {/if}
    </div>
</div>
