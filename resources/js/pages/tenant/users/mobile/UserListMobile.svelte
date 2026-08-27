<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Card, CardContent } from '@/components/ui/card';
    import { Link } from '@inertiajs/svelte';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Trash2 from 'lucide-svelte/icons/trash-2';

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

<div class="flex flex-col gap-3">
    {#each users as user (user.id)}
        <Card class="rounded-2xl border-border/70 bg-card p-4 shadow-2xs transition-all active:scale-[0.99]">
            <CardContent class="p-0 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary font-bold text-base">
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div class="flex flex-col">
                            <div class="flex items-center gap-1.5">
                                <span class="font-semibold text-foreground text-sm">{user.name}</span>
                                {#if currentUserId === user.id}
                                    <Badge variant="secondary" class="h-4.5 text-[10px] px-1.5 font-medium">Tú</Badge>
                                {/if}
                            </div>
                            <span class="text-xs text-muted-foreground truncate max-w-[200px]">{user.email}</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5 pt-1">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Rol Asignado</span>
                    <div class="flex flex-wrap gap-1.5">
                        {#each user.roles as role}
                            <Badge
                                variant={role.name === 'admin' ? 'default' : 'outline'}
                                class="h-6 text-xs font-medium rounded-md px-2"
                            >
                                {role.name}
                            </Badge>
                        {/each}
                        {#if user.roles.length === 0}
                            <span class="text-xs italic text-muted-foreground">Sin rol</span>
                        {/if}
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-border/40 pt-3 gap-2">
                    <Link href={`/tenant/users/${user.id}/edit`} class="flex-1">
                        <Button
                            variant="outline"
                            class="min-h-[44px] w-full gap-2 rounded-xl text-xs font-medium border-border/70 justify-center"
                        >
                            <Edit2 class="h-4 w-4" />
                            <span>Editar</span>
                        </Button>
                    </Link>

                    {#if currentUserId !== user.id}
                        <Button
                            variant="destructive"
                            class="min-h-[44px] min-w-[44px] px-3.5 gap-2 rounded-xl text-xs font-medium justify-center"
                            onclick={() => ondelete(user)}
                        >
                            <Trash2 class="h-4 w-4" />
                            <span class="sr-only">Eliminar</span>
                        </Button>
                    {/if}
                </div>
            </CardContent>
        </Card>
    {/each}
</div>
