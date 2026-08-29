<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $taxConditions = ['consumidor_final', 'responsable_inscripto', 'monotributo', 'exento'];

        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'tax_id' => (string) fake()->numerify('20########8'),
            'tax_condition' => fake()->randomElement($taxConditions),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'credit_limit' => fake()->randomFloat(2, 10000, 200000),
            'current_balance' => 0.00,
            'credit_due_days' => 30,
            'is_defaulter' => false,
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function defaulter(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_defaulter' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
