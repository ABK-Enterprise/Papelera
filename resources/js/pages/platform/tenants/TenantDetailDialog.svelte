<script lang="ts">
    import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogBody, DialogFooter, DialogClose } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Link } from '@inertiajs/svelte';
    import Icon from '@iconify/svelte';
    import X from 'lucide-svelte/icons/x';

    interface ModuleSummary {
        id: number;
        name: string;
        code: string;
    }

    interface TenantData {
        id: number;
        name: string;
        cuit_tax_id: string | null;
        is_active: boolean;
        users_count?: number;
        products_count?: number;
        modules?: ModuleSummary[];
        created_at?: string;
    }

    let {
        open = $bindable(false),
        tenant = null,
    }: {
        open: boolean;
        tenant: TenantData | null;
    } = $props();

    function formatDate(dateStr?: string): string {
        if (!dateStr) return '-';
        try {
            return new Intl.DateTimeFormat('es-AR', {
                dateStyle: 'medium',
            }).format(new Date(dateStr));
        } catch {
            return dateStr;
        }
    }
</script>

{#if tenant}
    <Dialog bind:open>
        <DialogContent class="max-w-md w-[calc(100%-2rem)] mx-4 p-5 rounded-2xl sm:rounded-3xl border border-border/80 shadow-lg">
            <DialogHeader class="pb-3 border-b border-border/60 shrink-0">
                <div class="flex items-center justify-between gap-2.5 sm:gap-3.5 w-full">
                    <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0 flex-1">
                        <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0 font-bold text-base sm:text-lg">
                            {tenant.name ? tenant.name.charAt(0).toUpperCase() : 'C'}
                        </div>
                        <div class="min-w-0 flex-1 flex flex-col justify-center">
                            <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                                {tenant.name}
                            </DialogTitle>
                            <span class="text-xs tabular-nums text-muted-foreground mt-1 truncate">
                                {tenant.cuit_tax_id ? `CUIT: ${tenant.cuit_tax_id}` : `ID: MER-${tenant.id.toString().padStart(5, '0')}`}
                            </span>
                        </div>
                    </div>
                    <DialogClose class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer shrink-0">
                        <X class="size-4" />
                    </DialogClose>
                </div>
            </DialogHeader>


            <DialogBody class="space-y-4 py-3 overflow-y-auto max-h-[60vh]">
                <!-- Estado -->
                <div class="flex items-center justify-between p-3 rounded-xl bg-muted/40 border border-border/50">
                    <span class="text-xs font-medium text-muted-foreground">Estado del comercio</span>
                    {#if tenant.is_active}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            Activo
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                            <span class="size-1.5 rounded-full bg-rose-500"></span>
                            Suspendido
                        </span>
                    {/if}
                </div>

                <!-- Métricas -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3.5 rounded-xl border border-border/60 bg-muted/20">
                        <div class="flex items-center gap-2 text-muted-foreground text-xs font-medium">
                            <Icon icon="solar:users-group-rounded-bold-duotone" class="size-3.5" />
                            <span>Usuarios</span>
                        </div>
                        <div class="text-xl font-bold tabular-nums text-foreground mt-1">
                            {tenant.users_count ?? 0}
                        </div>
                    </div>
                    <div class="p-3.5 rounded-xl border border-border/60 bg-muted/20">
                        <div class="flex items-center gap-2 text-muted-foreground text-xs font-medium">
                            <Icon icon="solar:box-minimalistic-bold-duotone" class="size-3.5" />
                            <span>Productos</span>
                        </div>
                        <div class="text-xl font-bold tabular-nums text-foreground mt-1">
                            {tenant.products_count ?? 0}
                        </div>
                    </div>
                </div>

                <!-- Fecha de alta -->
                <div class="flex items-center gap-2 text-xs text-muted-foreground p-3 rounded-xl border border-border/50 bg-muted/20">
                    <Icon icon="solar:calendar-bold-duotone" class="size-4 text-muted-foreground/70" />
                    <span>Fecha de alta: <span class="tabular-nums">{formatDate(tenant.created_at)}</span></span>
                </div>

                <!-- Módulos contratados -->
                {#if tenant.modules && tenant.modules.length > 0}
                    <div class="space-y-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Módulos contratados</span>
                        <div class="flex flex-wrap gap-1.5">
                            {#each tenant.modules as mod}
                                <Badge variant="outline" class="text-xs font-medium tracking-wide bg-muted/50 px-2.5 py-0.5">
                                    {mod.name}
                                </Badge>
                            {/each}
                        </div>
                    </div>
                {/if}
            </DialogBody>

            <DialogFooter class="pt-3 border-t border-border/50">
                <Link href={`/platform/tenants/${tenant.id}/edit`} class="w-full">
                    <Button class="w-full min-h-[44px] gap-2 rounded-xl text-sm font-medium shadow-sm">
                        <Icon icon="solar:pen-new-square-bold-duotone" class="size-4" />
                        <span>Editar datos del comercio</span>
                    </Button>
                </Link>
            </DialogFooter>
        </DialogContent>
    </Dialog>
{/if}
