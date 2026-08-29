<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAccountMovement>
 */
class CustomerAccountMovementFactory extends Factory
{
    protected $model = CustomerAccountMovement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'customer_order_id' => null,
            'created_by_user_id' => User::factory(),
            'type' => 'payment',
            'description' => 'Cobro / Abono de cuenta',
            'amount' => fake()->randomFloat(2, 500, 20000),
            'balance_after' => 0.00,
            'due_date' => null,
            'payment_method' => 'efectivo',
            'reference_number' => 'REC-'.fake()->numerify('#####'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
