<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Papelera Test', 'is_active' => true]);
    $this->warehouse = Warehouse::where('tenant_id', $this->tenant->id)->first();
});

test('imports catalog csv, creates categories and products with zero stock and sequential skus', function () {
    $csvContent = <<<CSV
GUANTES;;;;BOLSAS POLIETILENO;;;;;;;;
GUANTE DESCARTABLES PREMIUN X100;Unidad;$ 1.600,00;;Camiseta A/D 20x30 BLANCA X100 REF;Unidad;$ 1.500,00;;;;;;
LAMINA A/D 13X13 HAMBURGUESA XKG;Kilo;$ 12.500,00;;CINTA PAPEL 24MMX40MT RAPIFIX;Unidad;$ 2.600,00;;;;;;
TOALLAS INTERCALADAS BEIGE X2500;Caja;$ 7.460,00;;VASO POLIPAPEL 8OZ X100;Unidad;$ 6.500,00;;;;;;
HUEVERA EXP 12 COLOR 205X245 EH12AM X200;Paquete;$ 63.500,00;;PLATO PLASTICO 1KG KOVALPLAST X10;Unidad;$ 11.840,00;;;;;;
CSV;

    $tempCsv = tempnam(sys_get_temp_dir(), 'cat_test_') . '.csv';
    file_put_contents($tempCsv, $csvContent);

    $exitCode = Artisan::call('catalog:import-csv', [
        'file' => $tempCsv,
        '--tenant' => $this->tenant->id,
    ]);

    expect($exitCode)->toBe(0);

    // 1. Verify categories created
    $categories = Category::where('tenant_id', $this->tenant->id)->pluck('name');
    expect($categories)->toContain('Guantes')
        ->and($categories)->toContain('Bolsas Polietileno');

    // 2. Verify products created
    $guante = Product::where('tenant_id', $this->tenant->id)
        ->where('name', 'GUANTE DESCARTABLES PREMIUN X100')
        ->first();

    expect($guante)->not->toBeNull()
        ->and($guante->sale_type)->toBe('unit')
        ->and((float) $guante->price)->toBe(1600.00)
        ->and((float) $guante->stock)->toBe(0.00)
        ->and($guante->sku)->not->toBeNull()
        ->and($guante->sku)->toStartWith('GUA-');

    // 3. Verify Kilo mapped to weight
    $lamina = Product::where('tenant_id', $this->tenant->id)
        ->where('name', 'LAMINA A/D 13X13 HAMBURGUESA XKG')
        ->first();

    expect($lamina)->not->toBeNull()
        ->and($lamina->sale_type)->toBe('weight')
        ->and((float) $lamina->price)->toBe(12500.00);

    // 4. Verify Caja mapped to unit with description
    $toallas = Product::where('tenant_id', $this->tenant->id)
        ->where('name', 'TOALLAS INTERCALADAS BEIGE X2500')
        ->first();

    expect($toallas)->not->toBeNull()
        ->and($toallas->sale_type)->toBe('unit')
        ->and($toallas->description)->toBe('Venta por Caja')
        ->and((float) $toallas->price)->toBe(7460.00);

    // 5. Verify Paquete mapped to unit with description
    $huevera = Product::where('tenant_id', $this->tenant->id)
        ->where('name', 'HUEVERA EXP 12 COLOR 205X245 EH12AM X200')
        ->first();

    expect($huevera)->not->toBeNull()
        ->and($huevera->sale_type)->toBe('unit')
        ->and($huevera->description)->toBe('Venta por Paquete')
        ->and((float) $huevera->price)->toBe(63500.00);

    unlink($tempCsv);
});

test('catalog csv import is idempotent and updates existing product prices', function () {
    $initialCsv = <<<CSV
GUANTES;;;;;;;;;;;;
GUANTE DESCARTABLES PREMIUN X100;Unidad;$ 1.600,00;;;;;;;;;;
CSV;

    $tempCsv = tempnam(sys_get_temp_dir(), 'cat_test_') . '.csv';
    file_put_contents($tempCsv, $initialCsv);

    Artisan::call('catalog:import-csv', [
        'file' => $tempCsv,
        '--tenant' => $this->tenant->id,
    ]);

    $countAfterFirst = Product::where('tenant_id', $this->tenant->id)->count();
    expect($countAfterFirst)->toBe(1);

    // Now update price in CSV and re-import
    $updatedCsv = <<<CSV
GUANTES;;;;;;;;;;;;
GUANTE DESCARTABLES PREMIUN X100;Unidad;$ 1.950,00;;;;;;;;;;
CSV;

    file_put_contents($tempCsv, $updatedCsv);

    $exitCode = Artisan::call('catalog:import-csv', [
        'file' => $tempCsv,
        '--tenant' => $this->tenant->id,
    ]);

    expect($exitCode)->toBe(0);

    $countAfterSecond = Product::where('tenant_id', $this->tenant->id)->count();
    expect($countAfterSecond)->toBe(1);

    $product = Product::where('tenant_id', $this->tenant->id)
        ->where('name', 'GUANTE DESCARTABLES PREMIUN X100')
        ->first();

    expect((float) $product->price)->toBe(1950.00);

    unlink($tempCsv);
});
