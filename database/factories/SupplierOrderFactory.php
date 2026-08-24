<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierOrder>
 */
class SupplierOrderFactory extends Factory
{
    protected $model = SupplierOrder::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'supplier_id' => Supplier::factory(),
            'order_number' => '#OC-'.fake()->unique()->numerify('#####'),
            'status' => 'solicitado',
            'total_cost' => fake()->randomFloat(2, 1000, 100000),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
            'supplier_id' => Supplier::factory()->forTenant($tenant),
        ]);
    }
}
