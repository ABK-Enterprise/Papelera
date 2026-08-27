<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Card, CardContent } from '@/components/ui/card';
    import { Link } from '@inertiajs/svelte';
    import Shield from 'lucide-svelte/icons/shield';
    import Users from 'lucide-svelte/icons/users';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Trash2 from 'lucide-svelte/icons/trash-2';

    interface RoleItem {
        id: number;
        name: string;
        permissions_count: number;
        users_count: number;
        created_at: string;
    }

    let {
        roles = [],
        ondelete,
    }: {
        roles: RoleItem[];
        ondelete: (role: RoleItem) => void;
    } = $props();
</script>

<div class="flex flex-col gap-3">
    {#each roles as role (role.id)}
        <Card class="rounded-2xl border-border/70 bg-card p-4 shadow-2xs transition-all active:scale-[0.99]">
            <CardContent class="p-0 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Shield class="h-5 w-5 stroke-[1.75]" />
                        </div>
                        <div>
                            <span class="font-bold text-foreground text-sm">{role.name}</span>
                            <div class="flex items-center gap-1 text-xs text-muted-foreground mt-0.5">
                                <Users class="h-3 w-3" />
                                <span>{role.users_count} {role.users_count === 1 ? 'usuario' : 'usuarios'}</span>
                            </div>
                        </div>
                    </div>
                    <Badge variant="secondary" class="h-5 text-[11px] font-semibold px-2">
                        {role.permissions_count} permisos
                    </Badge>
                </div>

                <div class="flex items-center justify-between border-t border-border/40 pt-3 gap-2">
                    <Link href={`/tenant/roles/${role.id}/edit`} class="flex-1">
                        <Button
                            variant="outline"
                            class="min-h-[44px] w-full gap-2 rounded-xl text-xs font-medium border-border/70 justify-center"
                        >
                            <Edit2 class="h-4 w-4" />
                            <span>Editar Permisos</span>
                        </Button>
                    </Link>

                    <Button
                        variant="destructive"
                        class="min-h-[44px] min-w-[44px] px-3.5 gap-2 rounded-xl text-xs font-medium justify-center"
                        onclick={() => ondelete(role)}
                    >
                        <Trash2 class="h-4 w-4" />
                        <span class="sr-only">Eliminar Rol</span>
                    </Button>
                </div>
            </CardContent>
        </Card>
    {/each}
</div>
