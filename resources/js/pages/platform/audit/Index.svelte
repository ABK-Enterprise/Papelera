<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import AuditListDesktop from './desktop/AuditListDesktop.svelte';
    import AuditListMobile from './mobile/AuditListMobile.svelte';
    import AuditDetailModal from './AuditDetailModal.svelte';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import Search from 'lucide-svelte/icons/search';
    import X from 'lucide-svelte/icons/x';
    import Filter from 'lucide-svelte/icons/filter';
    import { router } from '@inertiajs/svelte';

    interface AuditLogItem {
        id: number;
        action: string;
        user_name?: string;
        user_role?: string;
        entity_label?: string;
        auditable_type?: string;
        auditable_id?: number;
        old_values?: Record<string, any> | null;
        new_values?: Record<string, any> | null;
        ip_address?: string;
        user_agent?: string;
        created_at: string;
        tenant?: { id: number; name: string } | null;
    }

    interface PaginatedResponse {
        data: AuditLogItem[];
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from?: number | null;
        to?: number | null;
    }

    interface TenantItem {
        id: number;
        name: string;
    }

    let {
        logs,
        tenants = [],
        filters = {},
    }: {
        logs: PaginatedResponse;
        tenants?: TenantItem[];
        filters?: {
            search?: string;
            tenant_id?: number | null;
            entity_type?: string;
            from_date?: string;
            to_date?: string;
        };
    } = $props();

    let search = $state(filters.search ?? '');
    let tenantId = $state(filters.tenant_id ? String(filters.tenant_id) : '');
    let entityType = $state(filters.entity_type ?? '');
    let fromDate = $state(filters.from_date ?? '');
    let toDate = $state(filters.to_date ?? '');

    let selectedLog = $state<AuditLogItem | null>(null);
    let isModalOpen = $state(false);

    let hasActiveFilters = $derived(
        Boolean(search || tenantId || entityType || fromDate || toDate)
    );

    function applyFilters() {
        router.get(
            '/platform/auditoria',
            {
                search: search.trim() || undefined,
                tenant_id: tenantId || undefined,
                entity_type: entityType || undefined,
                from_date: fromDate || undefined,
                to_date: toDate || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }

    function clearFilters() {
        search = '';
        tenantId = '';
        entityType = '';
        fromDate = '';
        toDate = '';
        router.get('/platform/auditoria', {}, { preserveState: true, preserveScroll: true });
    }

    function handleKeyDown(e: KeyboardEvent) {
        if (e.key === 'Enter') {
            applyFilters();
        }
    }

    function handleViewLog(log: AuditLogItem) {
        selectedLog = log;
        isModalOpen = true;
    }
</script>

<AppHead title="Historial de Auditoría" />

<div class="max-w-6xl w-full lg:w-[80%] mx-auto px-4 sm:px-6 py-6 space-y-6">
    <!-- Header del Módulo con separación estándar (24px a 30px) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-border/60 pb-5">
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
            <ShieldCheck class="size-7 text-primary shrink-0" />
            Historial de Auditoría
        </h1>

        <div class="flex items-center gap-2 self-start sm:self-auto text-xs text-muted-foreground font-medium tabular-nums">
            <span>{logs.total} {logs.total === 1 ? 'registro' : 'registros'}</span>
        </div>
    </div>

    <!-- Barra de Filtros Operativos -->
    <div class="rounded-xl border border-border/80 bg-card p-4 sm:p-5 space-y-3.5 shadow-2xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Buscador por texto -->
            <div class="relative sm:col-span-2">
                <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <Input
                    type="search"
                    placeholder="Buscar por acción, usuario o entidad..."
                    bind:value={search}
                    onkeydown={handleKeyDown}
                    class="h-10 pl-9 rounded-lg border-input bg-field text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                />
            </div>

            <!-- Selector de Comercio (Tenant) -->
            <div>
                <select
                    bind:value={tenantId}
                    onchange={applyFilters}
                    class="h-10 w-full rounded-lg border border-input bg-field px-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                >
                    <option value="">Todos los comercios</option>
                    {#each tenants as t (t.id)}
                        <option value={String(t.id)}>{t.name}</option>
                    {/each}
                </select>
            </div>

            <!-- Selector de Tipo de Entidad -->
            <div>
                <select
                    bind:value={entityType}
                    onchange={applyFilters}
                    class="h-10 w-full rounded-lg border border-input bg-field px-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                >
                    <option value="">Todas las entidades</option>
                    <option value="comercio">Comercio (Configuración)</option>
                    <option value="pedidos">Pedidos de Clientes</option>
                    <option value="facturacion">Facturación / Comprobantes</option>
                    <option value="proveedores">Órdenes de Proveedores</option>
                </select>
            </div>
        </div>

        <!-- Rango de Fechas y Botones de Acción -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-1 border-t border-border/40">
            <div class="flex flex-wrap items-center gap-2.5 text-xs text-muted-foreground">
                <span class="font-medium text-foreground">Fecha desde:</span>
                <input
                    type="date"
                    bind:value={fromDate}
                    onchange={applyFilters}
                    class="h-9 rounded-md border border-input bg-field px-2.5 text-xs text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:[color-scheme:dark]"
                />
                <span class="font-medium text-foreground">hasta:</span>
                <input
                    type="date"
                    bind:value={toDate}
                    onchange={applyFilters}
                    class="h-9 rounded-md border border-input bg-field px-2.5 text-xs text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:[color-scheme:dark]"
                />
            </div>

            <div class="flex items-center gap-2 self-end sm:self-auto">
                {#if hasActiveFilters}
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="min-h-[38px] gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
                        onclick={clearFilters}
                    >
                        <X class="size-3.5" />
                        <span>Limpiar filtros</span>
                    </Button>
                {/if}

                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    class="min-h-[38px] gap-1.5 text-sm font-medium"
                    onclick={applyFilters}
                >
                    <Filter class="size-3.5" />
                    <span>Aplicar</span>
                </Button>
            </div>
        </div>
    </div>

    <!-- Tabla Desglosada de Wide Cards (Desktop y Mobile) -->
    {#if logs.data.length > 0}
        <div class="hidden md:block">
            <AuditListDesktop logs={logs.data} onview={handleViewLog} />
        </div>
        <div class="block md:hidden">
            <AuditListMobile logs={logs.data} onview={handleViewLog} />
        </div>

        <!-- Paginación -->
        <div class="pt-2">
            <AppPagination pagination={logs} />
        </div>
    {:else}
        <!-- Estado Vacío -->
        <div class="rounded-xl border border-dashed border-border bg-card/60 p-10 text-center space-y-3">
            <div class="mx-auto size-12 rounded-full bg-muted/60 flex items-center justify-center text-muted-foreground">
                <ShieldCheck class="size-6" />
            </div>
            <h3 class="text-base font-semibold text-foreground">No se encontraron registros de auditoría</h3>
            <p class="text-xs sm:text-sm text-muted-foreground max-w-md mx-auto">
                {hasActiveFilters
                    ? 'No hay acciones registradas que coincidan con los filtros aplicados. Probá modificando o limpiando los criterios de búsqueda.'
                    : 'Aún no se han registrado eventos auditables en la plataforma.'}
            </p>
            {#if hasActiveFilters}
                <div class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="min-h-[38px] text-sm font-medium"
                        onclick={clearFilters}
                    >
                        Restablecer filtros
                    </Button>
                </div>
            {/if}
        </div>
    {/if}
</div>

<!-- Modal Detallado de Comparación Antiguo -> Nuevo -->
<AuditDetailModal
    bind:isOpen={isModalOpen}
    log={selectedLog}
    onclose={() => (selectedLog = null)}
/>
