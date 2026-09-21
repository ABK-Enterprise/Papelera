<?php

namespace Database\Factories;

use App\Models\CustomerOrder;
use App\Models\FiscalInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalInvoice>
 */
class FiscalInvoiceFactory extends Factory
{
    protected $model = FiscalInvoice::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_order_id' => CustomerOrder::factory(),
            'created_by_user_id' => User::factory(),
            'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_B,
            'punto_venta' => 1,
            'numero_comprobante' => fake()->unique()->numberBetween(1, 999999),
            'cae' => fake()->numerify('##############'),
            'cae_due_date' => now()->addDays(10)->toDateString(),
            'issue_date' => now()->toDateString(),
            'doc_tipo' => 99, // Consumidor Final
            'doc_nro' => '0',
            'receptor_condicion_iva_id' => 5, // Consumidor Final
            'receptor_name' => fake()->name(),
            'receptor_address' => fake()->address(),
            'concepto' => 1,
            'importe_total' => 1210.00,
            'importe_neto' => 1000.00,
            'importe_iva' => 210.00,
            'importe_exento' => 0.00,
            'importe_tributos' => 0.00,
            'associated_fiscal_invoice_id' => null,
            'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=sample',
            'arca_request_payload' => ['FECAESolicitar' => []],
            'arca_response_payload' => ['FECAESolicitarResult' => []],
            'status' => FiscalInvoice::STATUS_APROBADO,
            'notes' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function facturaA(): static
    {
        return $this->state(fn (array $attributes) => [
            'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_A,
            'doc_tipo' => 80,
            'doc_nro' => '30112233445',
            'receptor_condicion_iva_id' => 1,
        ]);
    }

    public function notaCreditoB(): static
    {
        return $this->state(fn (array $attributes) => [
            'cbte_tipo' => FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_B,
        ]);
    }
}
