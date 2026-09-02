<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Card, CardContent } from '@/components/ui/card';
    import { Link } from '@inertiajs/svelte';
    import Shield from 'lucide-svelte/icons/shield';
    import Users from 'lucide-svelte/icons/users';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import IconContainer from '@/components/common/IconContainer.svelte';

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
        <Card class="rounded-2xl border-border/70 bg-card p-4 shadow-2xs transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-accent/25 active:scale-[0.99]">

            <CardContent class="p-0 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <IconContainer size="md" variant="primary" icon="solar:shield-user-bold-duotone" />
                        <div>
                            <span class="font-semibold text-foreground text-sm">{role.name}</span>
                            <div class="flex items-center gap-1 text-xs text-muted-foreground mt-0.5 tabular-nums">
                                <Users class="h-3 w-3" />
                                <span>{role.users_count} {role.users_count === 1 ? 'usuario' : 'usuarios'}</span>
                            </div>
                        </div>
                    </div>
                    <Badge variant="secondary" class="h-5 text-xs font-medium tracking-wide px-2 tabular-nums">
                        {role.permissions_count} permisos
                    </Badge>
                </div>

                <div class="flex items-center justify-between border-t border-border/40 pt-3 gap-2">
                    <Link
                        href={`/tenant/roles/${role.id}/edit`}
                        class="flex-1 inline-flex items-center justify-center min-h-[44px] w-full gap-2 rounded-xl text-xs font-medium border border-primary-300/30 bg-primary-300/10 text-primary-600 hover:bg-primary-300/20 dark:bg-primary-400/10 dark:text-primary-300 dark:border-primary-400/30 dark:hover:bg-primary-400/20 transition-colors shadow-2xs"
                    >
                        <Edit2 class="h-4 w-4" />
                        <span>Editar rol</span>
                    </Link>


                    <Button
                        variant="destructive"
                        class="min-h-[44px] min-w-[44px] px-3.5 gap-2 rounded-xl text-xs font-medium justify-center"
                        onclick={() => ondelete(role)}
                    >
                        <Trash2 class="h-4 w-4" />
                        <span class="sr-only">Eliminar rol</span>
                    </Button>
                </div>
            </CardContent>
        </Card>
    {/each}
</div>
