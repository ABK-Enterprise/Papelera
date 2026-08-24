<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierOrderItem>
 */
class SupplierOrderItemFactory extends Factory
{
    protected $model = SupplierOrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(5, 50);
        $unitCost = fake()->randomFloat(2, 200, 3000);

        return [
            'supplier_order_id' => SupplierOrder::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'subtotal' => round($quantity * $unitCost, 2),
        ];
    }
}
