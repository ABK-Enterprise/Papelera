<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'category_id' => null,
            'name' => ucfirst(fake()->words(3, true)),
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'barcode' => fake()->ean13(),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 50, 5000),
            'cost_price' => fake()->randomFloat(2, 20, 2500),
            'iva_rate' => 21.00,
            'sale_type' => 'unit',
            'stock' => fake()->numberBetween(10, 100),
            'min_stock' => 5,
            'units_per_package' => 1,
            'package_quantity' => 1,
            'bulk_discount_type' => 'inherited',
            'custom_bulk_discount_percentage' => null,
            'image_url' => null,
            'is_active' => true,
        ];
    }

    public function weighable(float $packageWeightKg = 10.0): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_type' => 'weight',
            'package_quantity' => $packageWeightKg,
            'units_per_package' => $packageWeightKg,
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $category->tenant_id,
            'category_id' => $category->id,
        ]);
    }

    public function lowStock(int $stock = 3, int $minStock = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $stock,
            'min_stock' => $minStock,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }
}
