<?php

namespace Database\Factories;

use App\Models\CustomerOrder;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrder>
 */
class CustomerOrderFactory extends Factory
{
    protected $model = CustomerOrder::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'order_number' => '#PED-'.fake()->unique()->numerify('#####'),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'delivery_address' => fake()->address(),
            'status' => 'en_preparacion',
            'total_amount' => fake()->randomFloat(2, 500, 50000),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
