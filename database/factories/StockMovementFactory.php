<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'type' => 'income',
            'reason' => 'ingreso_manual',
            'quantity' => 10,
            'previous_stock' => 0,
            'current_stock' => 10,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'income',
            'reason' => 'ingreso_manual',
        ]);
    }

    public function outcome(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'outcome',
            'reason' => 'devolucion_cliente',
        ]);
    }
}
