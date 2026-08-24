<?php

namespace Database\Factories;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrderItem>
 */
class CustomerOrderItemFactory extends Factory
{
    protected $model = CustomerOrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->randomFloat(2, 100, 2000);

        return [
            'customer_order_id' => CustomerOrder::factory(),
            'product_id' => Product::factory(),
            'packaging_type' => 'paquete',
            'units_per_package' => 1,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => round($quantity * $unitPrice, 2),
        ];
    }
}
