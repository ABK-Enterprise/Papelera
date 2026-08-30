<script lang="ts">
    import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogBody, DialogFooter, DialogClose } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Link } from '@inertiajs/svelte';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Users from 'lucide-svelte/icons/users';
    import Package from 'lucide-svelte/icons/package';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Edit2 from 'lucide-svelte/icons/edit-2';
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
            <DialogHeader class="flex flex-row items-center justify-between pb-3 border-b border-border/50">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-base border border-primary/20 shrink-0">
                        {tenant.name ? tenant.name.charAt(0).toUpperCase() : 'C'}
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold text-foreground leading-snug">{tenant.name}</DialogTitle>
                        <span class="text-xs text-muted-foreground tabular-nums">
                            {tenant.cuit_tax_id ? `CUIT: ${tenant.cuit_tax_id}` : `ID: MER-${tenant.id.toString().padStart(5, '0')}`}
                        </span>
                    </div>
                </div>
                <DialogClose>
                    <button type="button" class="size-8 rounded-lg flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-colors">
                        <X class="size-4" />
                    </button>
                </DialogClose>
            </DialogHeader>

            <DialogBody class="space-y-4 py-3 overflow-y-auto max-h-[60vh]">
                <!-- Estado -->
                <div class="flex items-center justify-between p-3 rounded-xl bg-muted/40 border border-border/50">
                    <span class="text-xs font-medium text-muted-foreground">Estado del comercio</span>
                    {#if tenant.is_active}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            Activo
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                            <span class="size-1.5 rounded-full bg-rose-500"></span>
                            Suspendido
                        </span>
                    {/if}
                </div>

                <!-- M�tricas -->
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="p-3 rounded-xl border border-border/60 bg-card">
                        <div class="flex items-center gap-2 text-muted-foreground text-xs font-medium">
                            <Users class="size-3.5" />
                            <span>Usuarios</span>
                        </div>
                        <div class="text-xl font-bold tabular-nums text-foreground mt-1">
                            {tenant.users_count ?? 0}
                        </div>
                    </div>
                    <div class="p-3 rounded-xl border border-border/60 bg-card">
                        <div class="flex items-center gap-2 text-muted-foreground text-xs font-medium">
                            <Package class="size-3.5" />
                            <span>Productos</span>
                        </div>
                        <div class="text-xl font-bold tabular-nums text-foreground mt-1">
                            {tenant.products_count ?? 0}
                        </div>
                    </div>
                </div>

                <!-- Fecha de alta -->
                <div class="flex items-center gap-2 text-xs text-muted-foreground p-3 rounded-xl border border-border/50 bg-muted/20 tabular-nums">
                    <Calendar class="size-4 text-muted-foreground/70" />
                    <span>Fecha de alta: {formatDate(tenant.created_at)}</span>
                </div>

                <!-- M�dulos Contratados -->
                {#if tenant.modules && tenant.modules.length > 0}
                    <div class="space-y-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">M�dulos contratados</span>
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
                    <Button class="w-full min-h-[44px] gap-2 rounded-xl font-medium shadow-sm">
                        <Edit2 class="size-4" />
                        <span>Editar datos del comercio</span>
                    </Button>
                </Link>
            </DialogFooter>
        </DialogContent>
    </Dialog>
{/if}
