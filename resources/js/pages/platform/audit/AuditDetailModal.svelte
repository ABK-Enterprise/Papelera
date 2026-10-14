<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        DialogFooter,
    } from '@/components/ui/dialog';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import Clock from 'lucide-svelte/icons/clock';
    import User from 'lucide-svelte/icons/user';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Globe from 'lucide-svelte/icons/globe';

    interface AuditLogItem {
        id: number;
        action: string;
        user_name?: string;
        user_role?: string;
        entity_label?: string;
        auditable_type?: string;
        auditable_id?: number;
        old_values?: Record<string, any> | null;
        new_values?: Record<string, any> | null;
        ip_address?: string;
        user_agent?: string;
        created_at: string;
        tenant?: { id: number; name: string } | null;
    }

    let {
        isOpen = $bindable(false),
        log = null,
        onclose = () => {},
    }: {
        isOpen?: boolean;
        log?: AuditLogItem | null;
        onclose?: () => void;
    } = $props();

    function formatFieldLabel(key: string): string {
        const dictionary: Record<string, string> = {
            name: 'Nombre / Razón Social',
            cuit_tax_id: 'CUIT / Identificación Fiscal',
            cuit: 'CUIT',
            legal_name: 'Razón Social',
            trade_name: 'Nombre Comercial',
            address: 'Dirección Comercial',
            phone: 'Teléfono',
            email: 'Correo Electrónico',
            point_of_sale: 'Punto de Venta',
            status: 'Estado',
            fiscal_status: 'Estado Fiscal',
            is_active: 'Activo',
            total_amount: 'Importe Total',
            subtotal: 'Subtotal',
            tax_amount: 'Impuestos / IVA',
            discount_amount: 'Descuento',
            delivery_status: 'Estado de Reparto',
            payment_status: 'Estado de Pago',
            notes: 'Notas / Observaciones',
            role: 'Rol de Usuario',
        };

        return dictionary[key] ?? key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }

    function formatDisplayValue(val: any): string {
        if (val === null || val === undefined || val === '') return '(vacío)';
        if (typeof val === 'boolean') return val ? 'Sí (Activo)' : 'No (Inactivo)';
        if (typeof val === 'object') return JSON.stringify(val);
        return String(val);
    }

    let changedKeys = $derived.by(() => {
        if (!log) return [];
        const oldKeys = Object.keys(log.old_values ?? {});
        const newKeys = Object.keys(log.new_values ?? {});
        return Array.from(new Set([...oldKeys, ...newKeys]));
    });

    function handleOpenChange(open: boolean) {
        isOpen = open;
        if (!open) {
            onclose();
        }
    }
</script>

<Dialog open={isOpen} onOpenChange={handleOpenChange}>
    <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto p-5 sm:p-6 space-y-4">
        <DialogHeader class="pb-3 border-b border-border/60 shrink-0 text-left space-y-1">
            <div class="flex items-center gap-2.5 sm:gap-3.5 w-full">
                <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0 flex-1">
                    <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                        <ShieldAlert class="size-5 sm:size-6 text-primary" />
                    </div>
                    <div class="min-w-0 flex-1 flex flex-col justify-center">
                        <div class="flex items-center gap-2 flex-wrap">
                            <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                                {log?.action ?? 'Detalle de Acción'}
                            </DialogTitle>
                            <Badge variant="outline" class="border-primary/30 bg-primary/10 text-primary font-semibold text-xs tabular-nums">
                                Auditoría #{log?.id ?? ''}
                            </Badge>
                            {#if log?.user_role}
                                <Badge variant="secondary" class="text-xs uppercase tracking-wider font-semibold">
                                    {log.user_role}
                                </Badge>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </DialogHeader>

        {#if log}
            <div class="space-y-5">
                <!-- Metadata Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl border border-border/70 bg-muted/30 p-3.5 sm:p-4 text-xs">
                    <div class="flex items-center gap-2">
                        <User class="size-4 text-muted-foreground shrink-0" />
                        <span class="text-muted-foreground">Operador:</span>
                        <span class="text-sm font-medium text-foreground truncate">{log.user_name ?? 'Sistema'}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <Clock class="size-4 text-muted-foreground shrink-0" />
                        <span class="text-muted-foreground">Fecha:</span>
                        <span class="text-xs tabular-nums text-foreground">{log.created_at}</span>
                    </div>

                    {#if log.tenant}
                        <div class="flex items-center gap-2">
                            <Building2 class="size-4 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Comercio:</span>
                            <span class="font-medium text-foreground truncate">{log.tenant.name}</span>
                        </div>
                    {/if}

                    <div class="flex items-center gap-2">
                        <ShieldAlert class="size-4 text-muted-foreground shrink-0" />
                        <span class="text-muted-foreground">Entidad:</span>
                        <span class="font-medium text-foreground truncate">{log.entity_label ?? 'General'}</span>
                    </div>

                    {#if log.ip_address}
                        <div class="flex items-center gap-2 sm:col-span-2">
                            <Globe class="size-4 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Origen IP:</span>
                            <span class="text-muted-foreground tabular-nums text-xs">{log.ip_address}</span>
                        </div>
                    {/if}
                </div>

                <!-- Diffs Section -->
                <div class="space-y-3">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Campos Modificados ({changedKeys.length})
                    </h4>

                    {#if changedKeys.length > 0}
                        <div class="space-y-2.5">
                            {#each changedKeys as key}
                                {@const oldVal = log.old_values ? log.old_values[key] : undefined}
                                {@const newVal = log.new_values ? log.new_values[key] : undefined}
                                <div class="rounded-lg border border-border/70 bg-card p-3 space-y-1.5 shadow-2xs">
                                    <span class="text-xs font-semibold text-foreground block">
                                        {formatFieldLabel(key)}
                                    </span>

                                    {#if oldVal !== undefined && newVal !== undefined}
                                        <!-- Transformation row -->
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 text-xs">
                                            <div class="flex-1 rounded-md bg-destructive/10 border border-destructive/20 px-2.5 py-1 text-destructive line-through break-all">
                                                {formatDisplayValue(oldVal)}
                                            </div>
                                            <ArrowRight class="size-3.5 text-muted-foreground shrink-0 self-center hidden sm:block" />
                                            <div class="flex-1 rounded-md bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 text-emerald-700 dark:text-emerald-400 font-medium break-all">
                                                {formatDisplayValue(newVal)}
                                            </div>
                                        </div>
                                    {:else if newVal !== undefined}
                                        <!-- Only new value (Creation) -->
                                        <div class="rounded-md bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 text-xs text-emerald-700 dark:text-emerald-400 font-medium break-all">
                                            {formatDisplayValue(newVal)}
                                        </div>
                                    {:else if oldVal !== undefined}
                                        <!-- Only old value (Deletion) -->
                                        <div class="rounded-md bg-destructive/10 border border-destructive/20 px-2.5 py-1 text-xs text-destructive line-through break-all">
                                            {formatDisplayValue(oldVal)}
                                        </div>
                                    {/if}
                                </div>
                            {/each}
                        </div>
                    {:else}
                        <div class="rounded-lg border border-dashed border-border p-4 text-center text-xs text-muted-foreground">
                            Esta acción no registró modificaciones de atributos de datos.
                        </div>
                    {/if}
                </div>
            </div>
        {/if}

        <DialogFooter class="pt-2">
            <Button
                type="button"
                variant="outline"
                class="w-full sm:w-auto min-h-[44px] px-6 text-sm font-medium"
                onclick={() => handleOpenChange(false)}
            >
                Cerrar
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
