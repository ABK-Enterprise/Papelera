<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import RoleListDesktop from './desktop/RoleListDesktop.svelte';
    import RoleListMobile from './mobile/RoleListMobile.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import Shield from 'lucide-svelte/icons/shield';
    import Users from 'lucide-svelte/icons/users';

    interface RoleItem {
        id: number;
        name: string;
        permissions_count: number;
        users_count: number;
        created_at: string;
    }

    let { roles = [] }: { roles: RoleItem[] } = $props();

    let searchTerm = $state('');

    let filteredRoles = $derived.by(() => {
        const query = searchTerm.toLowerCase().trim();
        if (!query) return roles;
        return roles.filter((r) => r.name.toLowerCase().includes(query));
    });

    let roleToDelete = $state<RoleItem | null>(null);
    let isDeleteModalOpen = $state(false);
    let isDeleting = $state(false);

    function promptDelete(role: RoleItem) {
        roleToDelete = role;
        isDeleteModalOpen = true;
    }

    function executeDelete() {
        if (!roleToDelete) return;
        isDeleting = true;
        router.delete(`/tenant/roles/${roleToDelete.id}`, {
            onFinish: () => {
                isDeleting = false;
                isDeleteModalOpen = false;
                roleToDelete = null;
            },
        });
    }
</script>

<AppHead title="Roles y Permisos - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Encabezado y Acciones -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">Roles y Permisos</h1>
                <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium tracking-wide text-primary tabular-nums">
                    {roles.length} {roles.length === 1 ? 'perfil' : 'perfiles'}
                </span>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2.5">
            <Link href="/tenant/usuarios">
                <Button variant="secondary" class="min-h-[44px] gap-2 rounded-xl text-sm font-medium shadow-2xs">
                    <Users class="h-4 w-4" />
                    <span>Ver empleados</span>
                </Button>
            </Link>

            <Link href="/tenant/roles/create">
                <Button class="min-h-[44px] gap-2 rounded-xl text-sm font-medium shadow-xs">
                    <Shield class="h-4 w-4" />
                    <span>Nuevo rol</span>
                </Button>
            </Link>
        </div>
    </div>

    <!-- Barra de Búsqueda de Roles -->
    {#if roles.length > 0}
        <Card class="rounded-xl p-3 sm:p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.02)] border border-border/60">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="w-full sm:w-80">
                    <SearchCombobox
                        bind:value={searchTerm}
                        items={roles}
                        placeholder="Buscar por nombre de rol..."
                        onselect={(r) => {
                            searchTerm = r.name;
                        }}
                    >
                        {#snippet item(r)}
                            <div class="flex items-center justify-between gap-3 w-full min-w-0">
                                <span class="truncate font-medium text-foreground">{r.name}</span>
                                <div class="flex items-center gap-2 text-xs text-muted-foreground shrink-0 tabular-nums">
                                    <span>{r.permissions_count} perm.</span>
                                    <span>·</span>
                                    <span>{r.users_count} {r.users_count === 1 ? 'usuario' : 'usuarios'}</span>
                                </div>
                            </div>
                        {/snippet}
                    </SearchCombobox>
                </div>
                <div class="text-xs text-muted-foreground font-medium self-end sm:self-center">
                    Mostrando <strong class="text-foreground tabular-nums font-semibold">{filteredRoles.length}</strong> de <span class="tabular-nums">{roles.length}</span> {roles.length === 1 ? 'perfil' : 'perfiles'}
                </div>
            </div>
        </Card>
    {/if}

    <!-- Contenido Modular: Desktop vs Mobile -->
    {#if filteredRoles.length > 0}
        <div class="hidden md:block">
            <RoleListDesktop roles={filteredRoles} ondelete={promptDelete} />
        </div>
        <div class="block md:hidden">
            <RoleListMobile roles={filteredRoles} ondelete={promptDelete} />
        </div>
    {:else if searchTerm}
        <div class="rounded-xl border border-dashed border-border/80 p-8 text-center bg-card/40 flex flex-col items-center justify-center shadow-xs">
            <Shield class="size-8 text-muted-foreground/50 mb-2 stroke-[1.5]" />
            <h3 class="text-sm font-semibold text-foreground">No se encontraron perfiles de roles</h3>
            <p class="text-xs text-muted-foreground mt-1">Intenta con otro término o borra el filtro de búsqueda.</p>
            <Button variant="outline" size="sm" class="mt-4 text-xs min-h-[36px]" onclick={() => (searchTerm = '')}>
                Limpiar búsqueda
            </Button>
        </div>
    {:else}
        <EmptyState
            icon={Shield}
            title="No hay roles personalizados"
            description="Crea perfiles de trabajo como 'Cajero', 'Ventas' o 'Depósito' para asignar permisos selectivos a tu personal."
            actionLabel="Crear primer rol"
            actionHref="/tenant/roles/create"
        />
    {/if}
</div>

<!-- Modal de Confirmación de Eliminación -->
<ConfirmActionModal
    bind:open={isDeleteModalOpen}
    title="¿Eliminar rol de permisos?"
    description={`¿Estás seguro de eliminar el rol "${roleToDelete?.name}"? Los empleados que lo tengan asignado perderán de inmediato los permisos correspondientes.`}
    confirmText="Eliminar rol"
    cancelText="Cancelar"
    variant="danger"
    loading={isDeleting}
    onconfirm={executeDelete}
/>
