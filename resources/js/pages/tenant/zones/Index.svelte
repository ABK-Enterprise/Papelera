<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import ZoneListDesktop from './desktop/ZoneListDesktop.svelte';
    import ZoneListMobile from './mobile/ZoneListMobile.svelte';
    import ManageZoneModal, { type ShippingZoneItem } from './ManageZoneModal.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import MapPin from 'lucide-svelte/icons/map-pin';

    interface Props {
        zones: ShippingZoneItem[];
    }

    let { zones = [] }: Props = $props();

    let searchTerm = $state('');

    let filteredZones = $derived.by(() => {
        const query = searchTerm.toLowerCase().trim();
        if (!query) return zones;
        return zones.filter(
            (z) =>
                z.name.toLowerCase().includes(query) ||
                String(z.distance_km).includes(query) ||
                String(z.surcharge_percentage).includes(query)
        );
    });

    let isModalOpen = $state(false);
    let modalMode = $state<'create' | 'edit'>('create');
    let selectedZone = $state<ShippingZoneItem | null>(null);

    let isDeleteConfirmOpen = $state(false);
    let zoneToDelete = $state<ShippingZoneItem | null>(null);

    function openCreateModal() {
        modalMode = 'create';
        selectedZone = null;
        isModalOpen = true;
    }

    function openEditModal(zone: ShippingZoneItem) {
        modalMode = 'edit';
        selectedZone = zone;
        isModalOpen = true;
    }

    function openDeleteConfirm(zone: ShippingZoneItem) {
        zoneToDelete = zone;
        isDeleteConfirmOpen = true;
    }

    function handleDeleteConfirm() {
        if (!zoneToDelete) return;
        router.delete(`/tenant/configuracion/zonas/${zoneToDelete.id}`, {
            onSuccess: () => {
                isDeleteConfirmOpen = false;
                zoneToDelete = null;
            },
        });
    }
</script>

<AppHead title="Zonas de Envío" description="Gestión de tarifas y zonas de distribución" />

<div class="space-y-6">
    <!-- Cabecera del Módulo (Separación de 24-30px respecto al cuerpo) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <MapPin class="size-5" />
                </div>
                Zonas de Envío
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
                Configura los porcentajes de recargo y distancias de referencia para la distribución logística.
            </p>
        </div>

        <div>
            <Button
                variant="default"
                class="h-11 px-5 rounded-xl font-semibold gap-2 shadow-sm min-h-[44px] w-full sm:w-auto"
                onclick={openCreateModal}
            >
                <Plus class="size-4" />
                Nueva Zona de Envío
            </Button>
        </div>
    </div>

    <!-- Barra de Búsqueda -->
    <Card class="rounded-2xl border border-border/80 bg-card p-3.5 shadow-xs">
        <div class="w-full sm:max-w-md">
            <SearchCombobox
                bind:value={searchTerm}
                items={zones}
                searchKeys={['name']}
                placeholder="Buscar zona por nombre o distancia..."
                onclear={() => (searchTerm = '')}
            />
        </div>
    </Card>

    <!-- Listados Desglosados Desktop vs Mobile -->
    {#if filteredZones.length === 0}
        <EmptyState
            title={searchTerm ? 'Sin coincidencias' : 'No hay zonas de envío'}
            description={searchTerm
                ? `No se encontraron zonas que coincidan con "${searchTerm}".`
                : 'Crea tu primera zona de distribución para habilitar recargos automáticos en pedidos.'}
            actionLabel={searchTerm ? undefined : 'Crear primera zona'}
            onaction={searchTerm ? undefined : openCreateModal}
        />
    {:else}
        <!-- Vista Desktop (>= md) -->
        <div class="hidden md:block">
            <ZoneListDesktop
                zones={filteredZones}
                onedit={openEditModal}
                ondelete={openDeleteConfirm}
            />
        </div>

        <!-- Vista Mobile (< md) -->
        <div class="block md:hidden">
            <ZoneListMobile
                zones={filteredZones}
                onedit={openEditModal}
                ondelete={openDeleteConfirm}
            />
        </div>
    {/if}
</div>

<!-- Modal de Creación / Edición de Zona -->
<ManageZoneModal
    bind:open={isModalOpen}
    mode={modalMode}
    zone={selectedZone}
    onclose={() => (isModalOpen = false)}
/>

<!-- Modal de Confirmación de Eliminación -->
<ConfirmActionModal
    bind:open={isDeleteConfirmOpen}
    variant="destructive"
    title="¿Eliminar zona de envío?"
    description={`¿Estás seguro de que deseas eliminar la zona "${zoneToDelete?.name}"? Esta acción no se puede deshacer.`}
    confirmText="Eliminar Zona"
    onconfirm={handleDeleteConfirm}
/>
