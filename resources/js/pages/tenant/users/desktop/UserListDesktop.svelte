<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Link } from '@inertiajs/svelte';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import UserCheck from 'lucide-svelte/icons/user-check';
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
</script>

<div class="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-xs">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-border/60 bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
            <tr>
                <th scope="col" class="py-3.5 pl-6 pr-4">Empleado</th>
                <th scope="col" class="px-4 py-3.5">Roles Asignados</th>
                <th scope="col" class="px-4 py-3.5">Alta en Sistema</th>
                <th scope="col" class="py-3.5 pr-6 pl-4 text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border/40">
            {#each users as user (user.id)}
                <tr class="group transition-colors hover:bg-muted/30">
                    <td class="py-4 pl-6 pr-4">
                        <div class="flex items-center gap-3">
                            <IconContainer size="md" variant="primary">
                                {user.name.charAt(0).toUpperCase()}
                            </IconContainer>
                            <div class="flex flex-col">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-foreground">{user.name}</span>
                                    {#if currentUserId === user.id}
                                        <Badge variant="secondary" class="h-5 text-xs px-1.5 font-medium tracking-wide">Tú</Badge>
                                    {/if}
                                </div>
                                <span class="text-xs text-muted-foreground">{user.email}</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex flex-wrap gap-1.5">
                            {#each user.roles as role}
                                <Badge
                                    variant={role.name === 'admin' ? 'default' : 'outline'}
                                    class="h-6 text-xs font-medium tracking-wide rounded-md px-2.5"
                                >
                                    {role.name}
                                </Badge>
                            {/each}
                            {#if user.roles.length === 0}
                                <span class="text-xs italic text-muted-foreground">Sin rol asignado</span>
                            {/if}
                        </div>
                    </td>
                    <td class="px-4 py-4 text-xs text-muted-foreground tabular-nums">
                        {new Date(user.created_at).toLocaleDateString('es-AR', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                        })}
                    </td>
                    <td class="py-4 pr-6 pl-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <Link
                                href={`/tenant/usuarios/${user.id}/edit`}
                                class="inline-flex items-center justify-center min-h-[40px] min-w-[40px] px-3 gap-1.5 rounded-xl border border-primary-300/30 bg-primary-300/10 text-primary-600 hover:bg-primary-300/20 dark:bg-primary-400/10 dark:text-primary-300 dark:border-primary-400/30 dark:hover:bg-primary-400/20 text-xs font-medium transition-colors shadow-2xs"
                            >
                                <Edit2 class="h-3.5 w-3.5" />
                                <span>Editar</span>
                            </Link>


                            {#if currentUserId !== user.id}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onclick={() => ondelete(user)}
                                    class="min-h-[40px] min-w-[40px] gap-1.5 rounded-xl text-xs font-medium text-destructive hover:bg-destructive/10 hover:text-destructive transition-colors"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    <span>Eliminar</span>
                                </Button>
                            {/if}
                        </div>
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>
