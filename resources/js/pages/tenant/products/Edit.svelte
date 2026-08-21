<script lang="ts">
    import AppLayout from '@/layouts/AppLayout.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Link, useForm } from '@inertiajs/svelte';
    import Package from 'lucide-svelte/icons/package';
    import DollarSign from 'lucide-svelte/icons/dollar-sign';
    import Layers from 'lucide-svelte/icons/layers';

    interface CategoryOption {
        id: number;
        name: string;
    }

    interface ProductData {
        id: number;
        name: string;
        category_id: number | null;
        sku: string | null;
        barcode: string | null;
        description: string | null;
        price: string | number;
        cost_price: string | number | null;
        iva_rate: string | number;
        stock: number;
        min_stock: number;
        image_url: string | null;
        is_active: boolean;
    }

    let {
        product,
        categories = [],
    }: {
        product: ProductData;
        categories: CategoryOption[];
    } = $props();

    const form = useForm({
        name: product.name,
        category_id: product.category_id ?? ('' as string | number),
        sku: product.sku ?? '',
        barcode: product.barcode ?? '',
        description: product.description ?? '',
        price: product.price,
        cost_price: product.cost_price ?? '',
        iva_rate: Number(product.iva_rate ?? 21.00),
        stock: product.stock,
        min_stock: product.min_stock,
        image_url: product.image_url ?? '',
        is_active: product.is_active,
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.put(`/tenant/products/${product.id}`);
    }
</script>

<AppHead title="Editar Producto - Comercio" />

<AppLayout
    breadcrumbs={[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Productos', href: '/tenant/products' },
        { title: 'Editar Producto', href: `/tenant/products/${product.id}/edit` },
    ]}
>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-4xl mx-auto w-full">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Editar Producto</h1>
                <p class="text-sm text-muted-foreground">
                    Modifica los datos del artículo, precios y parámetros de stock.
                </p>
            </div>
            <Link href="/tenant/products">
                <Button variant="outline">Volver</Button>
            </Link>
        </div>

        <form onsubmit={submit} class="space-y-6">
            <!-- Sección 1: Información Principal -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-2">
                        <Package class="h-4 w-4 text-primary" />
                        1. Información General
                    </CardTitle>
                    <CardDescription>Datos identificatorios del producto en el catálogo.</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2 md:col-span-2">
                        <Label for="prod-name">Nombre del Producto *</Label>
                        <Input
                            id="prod-name"
                            bind:value={form.name}
                            required
                        />
                        {#if form.errors.name}
                            <p class="text-xs text-destructive">{form.errors.name}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="prod-category">Categoría</Label>
                        <select
                            id="prod-category"
                            bind:value={form.category_id}
                            class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="">Sin categoría asignada</option>
                            {#each categories as cat}
                                <option value={cat.id}>{cat.name}</option>
                            {/each}
                        </select>
                        {#if form.errors.category_id}
                            <p class="text-xs text-destructive">{form.errors.category_id}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="prod-sku">Código SKU / Interno</Label>
                        <Input
                            id="prod-sku"
                            bind:value={form.sku}
                        />
                        {#if form.errors.sku}
                            <p class="text-xs text-destructive">{form.errors.sku}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="prod-barcode">Código de Barras (EAN / UPC)</Label>
                        <Input
                            id="prod-barcode"
                            bind:value={form.barcode}
                        />
                        {#if form.errors.barcode}
                            <p class="text-xs text-destructive">{form.errors.barcode}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="prod-image">URL de Imagen (Opcional)</Label>
                        <Input
                            id="prod-image"
                            bind:value={form.image_url}
                        />
                        {#if form.errors.image_url}
                            <p class="text-xs text-destructive">{form.errors.image_url}</p>
                        {/if}
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label for="prod-description">Descripción Detallada</Label>
                        <textarea
                            id="prod-description"
                            bind:value={form.description}
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        ></textarea>
                    </div>
                </CardContent>
            </Card>

            <!-- Sección 2: Precios e Impuestos -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-2">
                        <DollarSign class="h-4 w-4 text-primary" />
                        2. Precios e Impuestos (ARCA)
                    </CardTitle>
                    <CardDescription>Valores de venta, costo y porcentaje de IVA aplicable.</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="prod-price">Precio de Venta ($) *</Label>
                        <Input
                            id="prod-price"
                            type="number"
                            step="0.01"
                            min="0"
                            bind:value={form.price}
                            required
                        />
                        {#if form.errors.price}
                            <p class="text-xs text-destructive">{form.errors.price}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="prod-cost">Precio de Costo ($)</Label>
                        <Input
                            id="prod-cost"
                            type="number"
                            step="0.01"
                            min="0"
                            bind:value={form.cost_price}
                        />
                        {#if form.errors.cost_price}
                            <p class="text-xs text-destructive">{form.errors.cost_price}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="prod-iva">Alícuota IVA (%)</Label>
                        <select
                            id="prod-iva"
                            bind:value={form.iva_rate}
                            class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value={21.00}>21.0% (General)</option>
                            <option value={10.50}>10.5% (Reducido)</option>
                            <option value={27.00}>27.0% (Servicios / Especial)</option>
                            <option value={0.00}>0.0% (Exento)</option>
                        </select>
                        {#if form.errors.iva_rate}
                            <p class="text-xs text-destructive">{form.errors.iva_rate}</p>
                        {/if}
                    </div>
                </CardContent>
            </Card>

            <!-- Sección 3: Control de Stock -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-2">
                        <Layers class="h-4 w-4 text-primary" />
                        3. Control de Inventario y Alertas
                    </CardTitle>
                    <CardDescription>Stock disponible y umbral de alerta de reposición.</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="prod-stock">Stock Actual</Label>
                        <Input
                            id="prod-stock"
                            type="number"
                            min="0"
                            bind:value={form.stock}
                        />
                        {#if form.errors.stock}
                            <p class="text-xs text-destructive">{form.errors.stock}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="prod-min-stock">Stock Mínimo (Alerta de Reposición)</Label>
                        <Input
                            id="prod-min-stock"
                            type="number"
                            min="0"
                            bind:value={form.min_stock}
                        />
                        {#if form.errors.min_stock}
                            <p class="text-xs text-destructive">{form.errors.min_stock}</p>
                        {/if}
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-3 pt-2">
                <Link href="/tenant/products">
                    <Button type="button" variant="outline">Cancelar</Button>
                </Link>
                <Button type="submit" disabled={form.processing} class="min-w-36 font-semibold">
                    {form.processing ? 'Guardando...' : 'Guardar Cambios'}
                </Button>
            </div>
        </form>
    </div>
</AppLayout>
