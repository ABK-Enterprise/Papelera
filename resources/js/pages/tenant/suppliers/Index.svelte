<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import { Link, router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Filter from 'lucide-svelte/icons/filter';
    import X from 'lucide-svelte/icons/x';
    import Truck from 'lucide-svelte/icons/truck';
    import Users from 'lucide-svelte/icons/users';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import XCircle from 'lucide-svelte/icons/x-circle';
    import SupplierListDesktop from './desktop/SupplierListDesktop.svelte';
    import SupplierListMobile from './mobile/SupplierListMobile.svelte';
    import CreateSupplierModal from './CreateSupplierModal.svelte';
    import ManageSupplierModal, { type SupplierItem } from './ManageSupplierModal.svelte';

    interface FilterProps {
        search?: string | null;
        status?: string | null;
    }

    let {
        suppliers = [],
        totalCount = 0,
        filters = {},
    }: {
        suppliers: SupplierItem[];
        totalCount?: number;
        filters?: FilterProps;
    } = $props();

    let searchTerm = $state(filters.search ?? '');
    let selectedStatus = $state(filters.status ?? '');

    let showCreateModal = $state(false);
    let showManageModal = $state(false);
    let selectedSupplier = $state<SupplierItem | null>(null);

    function applyFilters() {
        router.get(
            '/tenant/suppliers',
            {
                search: searchTerm || undefined,
                status: selectedStatus || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }

    function resetFilters() {
        searchTerm = '';
        selectedStatus = '';
        router.get('/tenant/suppliers');
    }

    function handleSelectSupplier(supplier: SupplierItem) {
        selectedSupplier = supplier;
        showManageModal = true;
    }

    let hasActiveFilters = $derived(Boolean(searchTerm || selectedStatus));
</script>

<AppHead title="Gestión de Proveedores - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Pestañas de Navegación del Módulo Proveedores -->
    <div class="flex items-center gap-2 border-b pb-3">
        <Link href="/tenant/suppliers">
            <Button variant="secondary" size="sm" class="gap-2 text-xs font-bold text-primary bg-primary/10 shadow-2xs">
                <Users class="size-4" />
                Directorio de Proveedores ({totalCount})
            </Button>
        </Link>
        <Link href="/tenant/suppliers/orders">
            <Button variant="ghost" size="sm" class="gap-2 text-xs font-semibold text-muted-foreground hover:text-foreground">
                <Truck class="size-4" />
                Órdenes de Compra
            </Button>
        </Link>
    </div>

    <!-- Header de la Página -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                <Truck class="size-7 text-primary" />
                Gestión de Proveedores
            </h1>
        </div>

        <div class="flex items-center gap-2.5">
            <Button class="gap-2 shadow-sm font-semibold" onclick={() => (showCreateModal = true)}>
                <Plus class="h-4 w-4" />
                + Añadir Proveedor
            </Button>
        </div>
    </div>

    <!-- Bento Card: Buscador y Filtros -->
        <Card class="rounded-xl p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.02)] border border-border/60">
            <div class="flex flex-col md:flex-row items-center justify-between gap-3">
                <!-- Buscador por Nombre, Contacto, Email o Dirección -->
                <div class="relative w-full md:w-96">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <input
                        type="text"
                        bind:value={searchTerm}
                        onkeydown={(e) => e.key === 'Enter' && applyFilters()}
                        placeholder="Buscar por nombre, teléfono, email..."
                        class="h-10 w-full rounded-lg border border-input bg-muted/20 pl-9 pr-4 text-sm placeholder:text-muted-foreground focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary transition-all"
                    />
                </div>

                <!-- Selectores de Filtro -->
                <div class="flex items-center gap-2.5 w-full md:w-auto">
                    <select
                        bind:value={selectedStatus}
                        onchange={applyFilters}
                        class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[150px]"
                    >
                        <option value="">Estado: Todos</option>
                        <option value="active">Solo Activos</option>
                        <option value="inactive">Solo Inactivos</option>
                    </select>

                    <Button variant="secondary" size="sm" class="h-10 px-3.5 text-xs gap-1.5" onclick={applyFilters}>
                        <Filter class="h-3.5 w-3.5" />
                        Filtrar
                    </Button>

                    {#if hasActiveFilters}
                        <Button variant="ghost" size="sm" class="h-10 px-2 text-xs text-muted-foreground hover:text-foreground gap-1" onclick={resetFilters}>
                            <X class="h-3.5 w-3.5" />
                            Limpiar
                        </Button>
                    {/if}
                </div>
            </div>
        </Card>

        <!-- Lista en formato Wide Cards (Desglose Desktop) -->
        <div class="hidden md:block">
            <SupplierListDesktop
                {suppliers}
                onSelectSupplier={handleSelectSupplier}
                onOpenCreate={() => (showCreateModal = true)}
            />
        </div>

        <!-- Lista en formato Wide Cards (Desglose Mobile) -->
        <div class="block md:hidden">
            <SupplierListMobile
                {suppliers}
                onSelectSupplier={handleSelectSupplier}
                onOpenCreate={() => (showCreateModal = true)}
            />
        </div>
    </div>

    <!-- Modales de Operación -->
    <CreateSupplierModal bind:open={showCreateModal} />

    <ManageSupplierModal bind:open={showManageModal} supplier={selectedSupplier} />
