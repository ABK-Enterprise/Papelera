<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Link, router } from '@inertiajs/svelte';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Shield from 'lucide-svelte/icons/shield';
    import IconContainer from '@/components/common/IconContainer.svelte';

    interface RoleItem {
        id: number;
        name: string;
    }

    interface UserItem {
        id: number;
        name: string;
        email: string;
        roles: RoleItem[];
        created_at: string;
    }

    let {
        users = [],
        currentUserId,
        ondelete,
    }: {
        users: UserItem[];
        currentUserId?: number;
        ondelete: (user: UserItem) => void;
    } = $props();

    function navigateToEdit(userId: number) {
        router.visit(`/tenant/usuarios/${userId}/edit`);
    }
</script>

<div class="flex flex-col gap-3">
    {#each users as user (user.id)}
        <div
            role="button"
            tabindex="0"
            onclick={() => navigateToEdit(user.id)}
            onkeydown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    navigateToEdit(user.id);
                }
            }}
            class="flex items-center justify-between p-4 sm:p-5 rounded-2xl border border-border/70 bg-card shadow-xs transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-accent/25 hover:border-border cursor-pointer gap-4 text-left select-none"
        >
            <!-- Lado Izquierdo: Avatar + Información del Empleado -->
            <div class="flex items-center gap-4 min-w-0 flex-1">
                <IconContainer size="lg" variant="primary">
                    <span class="text-sm font-semibold">{user.name.charAt(0).toUpperCase()}</span>
                </IconContainer>

                <div class="flex flex-col min-w-0 space-y-1">
                    <div class="flex items-center gap-2">
                        <h4 class="text-sm font-medium text-foreground truncate leading-snug">
                            {user.name}
                        </h4>
                        {#if currentUserId === user.id}
                            <Badge variant="secondary" class="h-5 text-xs px-2 font-medium tracking-wide">
                                Tú
                            </Badge>
                        {/if}
                    </div>
                    <span class="text-xs text-muted-foreground truncate">{user.email}</span>
                </div>
            </div>

            <!-- Centro: Roles Asignados -->
            <div class="hidden md:flex flex-col items-start gap-1 min-w-[180px]">
                <span class="text-xs font-medium text-muted-foreground uppercase tracking-wide flex items-center gap-1">
                    <Shield class="size-3 text-primary" />
                    Roles Asignados
                </span>
                <div class="flex flex-wrap gap-1.5">
                    {#each user.roles as role}
                        <Badge
                            variant={role.name === 'admin' ? 'default' : 'outline'}
                            class="h-6 text-xs font-medium tracking-wide rounded-lg px-2.5"
                        >
                            {role.name}
                        </Badge>
                    {/each}
                    {#if user.roles.length === 0}
                        <span class="text-xs italic text-muted-foreground">Sin rol asignado</span>
                    {/if}
                </div>
            </div>

            <!-- Centro-Derecha: Fecha de Alta -->
            <div class="hidden lg:flex flex-col items-start gap-0.5 min-w-[140px] text-xs font-medium tracking-wide text-muted-foreground tabular-nums">
                <span class="text-xs font-medium text-muted-foreground uppercase tracking-wide flex items-center gap-1">
                    <Calendar class="size-3 text-muted-foreground" />
                    Alta en Sistema
                </span>
                <span class="text-foreground text-xs font-medium tracking-wide">
                    {new Date(user.created_at).toLocaleDateString('es-AR', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                    })}
                </span>
            </div>

            <!-- Lado Derecho: Acciones -->
            <div class="flex items-center justify-end gap-2 shrink-0">
                <Link
                    href={`/tenant/usuarios/${user.id}/edit`}
                    onclick={(e) => e.stopPropagation()}
                    class="inline-flex items-center justify-center min-h-[38px] px-4 gap-1.5 rounded-xl border border-primary/30 bg-primary/15 text-foreground hover:bg-primary/25 dark:text-sky-100 dark:border-primary/40 text-sm font-medium transition-colors shadow-2xs"
                >
                    <Edit2 class="h-3.5 w-3.5" />
                    <span>Editar</span>
                </Link>

                {#if currentUserId !== user.id}
                    <Button
                        variant="ghost"
                        size="sm"
                        onclick={(e) => {
                            e.stopPropagation();
                            ondelete(user);
                        }}
                        class="min-h-[38px] min-w-[38px] px-3 gap-1.5 rounded-xl text-sm font-medium text-destructive hover:bg-destructive/10 hover:text-destructive transition-colors justify-center"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        <span class="sr-only">Eliminar</span>
                    </Button>
                {/if}
            </div>
        </div>
    {/each}
</div>

