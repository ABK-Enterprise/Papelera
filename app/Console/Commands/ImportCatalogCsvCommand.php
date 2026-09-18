<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportCatalogCsvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'catalog:import-csv {file? : Ruta al archivo CSV} {--tenant= : ID del tenant específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa y actualiza el catálogo de productos desde un archivo CSV con soporte para tablas duales y auto-generación de SKUs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file') ?: base_path('designs/Lista de precios - MAYO 2026.csv');

        if (! file_exists($filePath)) {
            $this->error("El archivo CSV no existe en la ruta: {$filePath}");

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant');
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
        } else {
            $tenant = Tenant::where('is_active', true)->first() ?: Tenant::first();
        }

        if (! $tenant) {
            $this->error('No se encontró ningún tenant activo en la base de datos.');

            return self::FAILURE;
        }

        $defaultWarehouse = Warehouse::where('tenant_id', $tenant->id)->where('is_default', true)->first()
            ?: Warehouse::where('tenant_id', $tenant->id)->first();

        $this->info("Importando catálogo para el tenant: [{$tenant->id}] {$tenant->name}");
        $this->info("Depósito destino: " . ($defaultWarehouse ? "[{$defaultWarehouse->id}] {$defaultWarehouse->name}" : 'Ninguno'));

        $rawContent = file_get_contents($filePath);
        if (! mb_check_encoding($rawContent, 'UTF-8')) {
            $rawContent = mb_convert_encoding($rawContent, 'UTF-8', 'Windows-1252');
        }

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $rawContent);
        rewind($handle);

        $currentCategoryCol0 = null;
        $currentCategoryCol4 = null;

        $createdCategoriesCount = 0;
        $createdProductsCount = 0;
        $updatedProductsCount = 0;

        // Cache category objects to avoid repeated queries
        $categoriesCache = Category::where('tenant_id', $tenant->id)
            ->get()
            ->keyBy(fn ($cat) => mb_strtoupper(trim($cat->name), 'UTF-8'));

        // Cache existing SKUs for fast lookup
        $existingSkus = Product::where('tenant_id', $tenant->id)
            ->whereNotNull('sku')
            ->pluck('sku')
            ->map(fn ($s) => mb_strtoupper($s, 'UTF-8'))
            ->flip()
            ->toArray();

        // SKU sequence counters per prefix
        $skuCounters = [];

        DB::beginTransaction();

        try {
            $rowNumber = 0;
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $rowNumber++;

                // 1. Process Column Set 0..2
                $name0 = trim($row[0] ?? '');
                $type0 = trim($row[1] ?? '');
                $price0 = trim($row[2] ?? '');

                if ($name0 !== '') {
                    if ($type0 === '' && $price0 === '') {
                        // Category Header
                        $currentCategoryCol0 = $this->getOrCreateCategory($name0, $tenant->id, $categoriesCache, $createdCategoriesCount);
                    } elseif ($type0 !== '') {
                        $this->processProduct(
                            $name0,
                            $type0,
                            $price0,
                            $currentCategoryCol0,
                            $tenant->id,
                            $defaultWarehouse?->id,
                            $existingSkus,
                            $skuCounters,
                            $createdProductsCount,
                            $updatedProductsCount
                        );
                    }
                }

                // 2. Process Column Set 4..6
                $name4 = trim($row[4] ?? '');
                $type4 = trim($row[5] ?? '');
                $price4 = trim($row[6] ?? '');

                if ($name4 !== '') {
                    if ($type4 === '' && $price4 === '') {
                        // Category Header
                        $currentCategoryCol4 = $this->getOrCreateCategory($name4, $tenant->id, $categoriesCache, $createdCategoriesCount);
                    } elseif ($type4 !== '') {
                        $this->processProduct(
                            $name4,
                            $type4,
                            $price4,
                            $currentCategoryCol4,
                            $tenant->id,
                            $defaultWarehouse?->id,
                            $existingSkus,
                            $skuCounters,
                            $createdProductsCount,
                            $updatedProductsCount
                        );
                    }
                }
            }

            fclose($handle);
            DB::commit();

            $this->newLine();
            $this->info("✓ Importación completada con éxito:");
            $this->line("  - Categorías creadas: {$createdCategoriesCount}");
            $this->line("  - Productos nuevos creados: {$createdProductsCount}");
            $this->line("  - Productos existentes actualizados: {$updatedProductsCount}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            $this->error("Error durante la importación en la línea {$rowNumber}: " . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function getOrCreateCategory(
        string $rawName,
        int $tenantId,
        &$categoriesCache,
        int &$createdCategoriesCount
    ): Category {
        $cleanName = mb_convert_case(trim($rawName), MB_CASE_TITLE, 'UTF-8');
        $lookupKey = mb_strtoupper($cleanName, 'UTF-8');

        if (isset($categoriesCache[$lookupKey])) {
            return $categoriesCache[$lookupKey];
        }

        $slug = Str::slug($cleanName);
        $originalSlug = $slug;
        $counter = 1;
        while (Category::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        $category = Category::create([
            'tenant_id' => $tenantId,
            'name' => $cleanName,
            'slug' => $slug,
            'description' => "Categoría {$cleanName} importada desde catálogo",
            'is_active' => true,
        ]);

        $categoriesCache[$lookupKey] = $category;
        $createdCategoriesCount++;

        return $category;
    }

    private function processProduct(
        string $name,
        string $rawType,
        string $rawPrice,
        ?Category $category,
        int $tenantId,
        ?int $warehouseId,
        array &$existingSkus,
        array &$skuCounters,
        int &$createdProductsCount,
        int &$updatedProductsCount
    ): void {
        $price = $this->parseArgentinePrice($rawPrice);
        $typeUpper = mb_strtoupper(trim($rawType), 'UTF-8');

        // Map sale_type according to business rules
        $saleType = match ($typeUpper) {
            'KILO' => 'weight',
            default => 'unit', // Unidad, Caja, Paquete are stored as 'unit'
        };

        // Note: Caja and Paquete are treated as unit with package packaging configuration
        $description = null;
        $packageQuantity = 1;
        if ($typeUpper === 'CAJA') {
            $description = 'Venta por Caja';
        } elseif ($typeUpper === 'PAQUETE') {
            $description = 'Venta por Paquete';
        }

        // Check if product already exists for tenant with same name and sale_type
        $product = Product::where('tenant_id', $tenantId)
            ->where('name', $name)
            ->where('sale_type', $saleType)
            ->first();

        if ($product) {
            // Update price and category
            $product->update([
                'price' => $price,
                'category_id' => $category?->id ?? $product->category_id,
                'description' => $description ?: $product->description,
            ]);
            $updatedProductsCount++;

            return;
        }

        // Generate clean category-based sequential SKU
        $sku = $this->generateSequentialSku($category?->name ?? 'GEN', $existingSkus, $skuCounters);

        $newProduct = Product::create([
            'tenant_id' => $tenantId,
            'category_id' => $category?->id,
            'name' => $name,
            'sale_type' => $saleType,
            'sku' => $sku,
            'barcode' => null,
            'description' => $description,
            'price' => $price,
            'cost_price' => null,
            'iva_rate' => 21.00,
            'stock' => 0.00,
            'min_stock' => 5.00,
            'units_per_package' => $packageQuantity,
            'package_quantity' => $packageQuantity,
            'bulk_discount_type' => 'inherited',
            'custom_bulk_discount_percentage' => null,
            'image_url' => null,
            'thumbnail_url' => null,
            'is_active' => true,
        ]);

        if ($warehouseId) {
            ProductWarehouse::create([
                'tenant_id' => $tenantId,
                'product_id' => $newProduct->id,
                'warehouse_id' => $warehouseId,
                'stock' => 0.00,
                'min_stock' => 5.00,
            ]);
        }

        $createdProductsCount++;
    }

    private function parseArgentinePrice(string $rawPrice): float
    {
        // Examples: "$ 1.600,00", "$ 245,00", "15450,00", "$ 63.500,00"
        $cleaned = preg_replace('/[^\d,]/', '', $rawPrice);
        $normalized = str_replace(',', '.', $cleaned);

        return (float) ($normalized ?: 0.00);
    }

    private function generateSequentialSku(
        string $categoryName,
        array &$existingSkus,
        array &$skuCounters
    ): string {
        // Build prefix from category name: e.g. "GUANTES" -> "GUA", "BOLSAS POLIETILENO" -> "BOL"
        $words = preg_split('/[\s,\-\/]+/', mb_strtoupper($categoryName, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
        $prefix = '';
        if (count($words) >= 2) {
            $prefix = mb_substr($words[0], 0, 2) . mb_substr($words[1], 0, 2);
        } else {
            $prefix = mb_substr($words[0] ?? 'PRD', 0, 3);
        }

        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?: 'PRD';
        if (mb_strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }

        if (! isset($skuCounters[$prefix])) {
            $skuCounters[$prefix] = 1;
        }

        do {
            $seq = str_pad((string) $skuCounters[$prefix], 3, '0', STR_PAD_LEFT);
            $candidate = "{$prefix}-{$seq}";
            $skuCounters[$prefix]++;
            $upperCandidate = mb_strtoupper($candidate, 'UTF-8');
        } while (isset($existingSkus[$upperCandidate]));

        $existingSkus[$upperCandidate] = true;

        return $candidate;
    }
}
