<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
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

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
    {#each roles as role (role.id)}
        <Card class="flex flex-col justify-between rounded-2xl border border-border/70 bg-card shadow-xs transition-all hover:shadow-md hover:border-border">
            <CardHeader class="pb-3.5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <IconContainer size="md" variant="primary" icon="solar:shield-user-bold-duotone" />
                        <div>
                            <CardTitle class="text-base font-semibold text-foreground">{role.name}</CardTitle>
                            <CardDescription class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5 tabular-nums">
                                <Users class="h-3 w-3" />
                                <span>{role.users_count} {role.users_count === 1 ? 'usuario asignado' : 'usuarios asignados'}</span>
                            </CardDescription>
                        </div>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="space-y-4 pt-0">
                <div class="flex items-center justify-between rounded-xl bg-muted/40 p-2.5 text-xs text-muted-foreground">
                    <span class="font-medium">Permisos habilitados:</span>
                    <Badge variant="secondary" class="font-medium text-xs tracking-wide text-foreground px-2 py-0.5 tabular-nums">
                        {role.permissions_count} permisos
                    </Badge>
                </div>

                <div class="flex items-center justify-between border-t border-border/40 pt-3 gap-2">
                    <Link
                        href={`/tenant/roles/${role.id}/edit`}
                        class="flex-1 inline-flex items-center justify-center min-h-[40px] w-full gap-1.5 rounded-xl border border-primary-300/30 bg-primary-300/10 text-primary-600 hover:bg-primary-300/20 dark:bg-primary-400/10 dark:text-primary-300 dark:border-primary-400/30 dark:hover:bg-primary-400/20 text-xs font-medium transition-colors shadow-2xs"
                    >
                        <Edit2 class="h-3.5 w-3.5" />
                        <span>Editar rol</span>
                    </Link>


                    <Button
                        variant="ghost"
                        size="sm"
                        onclick={() => ondelete(role)}
                        class="min-h-[40px] min-w-[40px] px-3 gap-1.5 rounded-xl text-xs font-medium text-destructive hover:bg-destructive/10 hover:text-destructive justify-center"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        <span class="sr-only">Eliminar</span>
                    </Button>
                </div>
            </CardContent>
        </Card>
    {/each}
</div>
