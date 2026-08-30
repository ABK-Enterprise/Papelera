<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerMovementSeeder extends Seeder
{
    /**
     * Run the database seeds for customer account movements.
     */
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $user = User::where('tenant_id', $tenant->id)->first();
            $userId = $user?->id;

            // 1. Librería El Ateneo Central (flujo regular de ventas a crédito y pagos por transferencia)
            $ateneo = Customer::where('tenant_id', $tenant->id)
                ->where('name', 'Librería El Ateneo Central')
                ->first();

            if ($ateneo) {
                CustomerAccountMovement::where('tenant_id', $tenant->id)
                    ->where('customer_id', $ateneo->id)
                    ->delete();

                $order1 = CustomerOrder::where('tenant_id', $tenant->id)
                    ->where('customer_id', $ateneo->id)
                    ->first();

                // Movimiento 1: Venta a crédito inicial
                CustomerAccountMovement::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $ateneo->id,
                    'customer_order_id' => $order1?->id,
                    'created_by_user_id' => $userId,
                    'type' => 'charge',
                    'description' => 'Venta a crédito - Pedido #'.($order1?->order_number ?? 'PED-1001'),
                    'amount' => 45000.00,
                    'balance_after' => 45000.00,
                    'due_date' => now()->subDays(10)->toDateString(),
                    'payment_method' => 'cuenta_corriente',
                    'reference_number' => $order1?->order_number ?? 'PED-1001',
                    'notes' => 'Factura A-0001-00004521',
                    'created_at' => now()->subDays(20),
                    'updated_at' => now()->subDays(20),
                ]);

                // Movimiento 2: Pago por transferencia
                CustomerAccountMovement::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $ateneo->id,
                    'customer_order_id' => null,
                    'created_by_user_id' => $userId,
                    'type' => 'payment',
                    'description' => 'Cobro / Abono (transferencia) - Banco Santander',
                    'amount' => 45000.00,
                    'balance_after' => 0.00,
                    'due_date' => null,
                    'payment_method' => 'transferencia',
                    'reference_number' => 'TRF-448920',
                    'notes' => 'Comprobante recibido por tesorería',
                    'created_at' => now()->subDays(15),
                    'updated_at' => now()->subDays(15),
                ]);

                // Movimiento 3: Nueva venta a crédito
                CustomerAccountMovement::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $ateneo->id,
                    'customer_order_id' => null,
                    'created_by_user_id' => $userId,
                    'type' => 'charge',
                    'description' => 'Venta a crédito - Pedido #PED-1002',
                    'amount' => 68500.00,
                    'balance_after' => 68500.00,
                    'due_date' => now()->addDays(23)->toDateString(),
                    'payment_method' => 'cuenta_corriente',
                    'reference_number' => 'PED-1002',
                    'notes' => 'Factura A-0001-00004589',
                    'created_at' => now()->subDays(7),
                    'updated_at' => now()->subDays(7),
                ]);

                // Movimiento 4: Pago parcial por transferencia
                CustomerAccountMovement::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $ateneo->id,
                    'customer_order_id' => null,
                    'created_by_user_id' => $userId,
                    'type' => 'payment',
                    'description' => 'Cobro / Abono parcial (transferencia)',
                    'amount' => 30000.00,
                    'balance_after' => 38500.00,
                    'due_date' => null,
                    'payment_method' => 'transferencia',
                    'reference_number' => 'TRF-882190',
                    'notes' => 'Pago a cuenta de factura A-0001-00004589',
                    'created_at' => now()->subDays(2),
                    'updated_at' => now()->subDays(2),
                ]);

                $ateneo->update([
                    'current_balance' => 38500.00,
                    'is_defaulter' => false,
                ]);
            }

            // 2. Gráfica Central SRL (compras grandes a crédito, cheque diferido, bonificación por volumen)
            $grafica = Customer::where('tenant_id', $tenant->id)
                ->where('name', 'Gráfica Central SRL')
                ->first();

            if ($grafica) {
                CustomerAccountMovement::where('tenant_id', $tenant->id)
                    ->where('customer_id', $grafica->id)
                    ->delete();

                $order2 = CustomerOrder::where('tenant_id', $tenant->id)
                    ->where('customer_id', $grafica->id)
                    ->first();

                // Movimiento 1: Compra grande a crédito
                CustomerAccountMovement::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $grafica->id,
                    'customer_order_id' => $order2?->id,
                    'created_by_user_id' => $userId,
                    'type' => 'charge',
                    'description' => 'Venta a crédito mayorista - Pedido #'.($order2?->order_number ?? 'PED-1003'),
                    'amount' => 145000.00,
                    'balance_after' => 145000.00,
                    'due_date' => now()->addDays(12)->toDateString(),
                    'payment_method' => 'cuenta_corriente',
                    'reference_number' => $order2?->order_number ?? 'PED-1003',
                    'notes' => 'Entrega de resmas paletizadas Factura A-0001-00004530',
                    'created_at' => now()->subDays(18),
                    'updated_at' => now()->subDays(18),
                ]);

                // Movimiento 2: Pago con cheque diferido
                CustomerAccountMovement::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $grafica->id,
                    'customer_order_id' => null,
                    'created_by_user_id' => $userId,
                    'type' => 'payment',
                    'description' => 'Cobro / Abono (cheque) - Cheque Diferido #554411 Banco Galicia 30 días',
                    'amount' => 80000.00,
                    'balance_after' => 65000.00,
                    'due_date' => null,
                    'payment_method' => 'cheque',
                    'reference_number' => 'CHQ-554411',
                    'notes' => 'Vencimiento cheque: '.now()->addDays(15)->toDateString(),
                    'created_at' => now()->subDays(10),
                    'updated_at' => now()->subDays(10),
                ]);

                // Movimiento 3: Bonificación por volumen (Ajuste de Crédito)
                CustomerAccountMovement::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $grafica->id,
                    'customer_order_id' => null,
                    'created_by_user_id' => $userId,
                    'type' => 'adjustment',
                    'description' => 'Bonificación por volumen comercial trimestral',
                    'amount' => 15000.00,
                    'balance_after' => 50000.00,
                    'due_date' => null,
                    'payment_method' => 'ajuste',
                    'reference_number' => 'AJU-BONIF-01',
                    'notes' => 'Descuento especial acordado con gerencia comercial',
                    'created_at' => now()->subDays(5),
                    'updated_at' => now()->subDays(5),
                ]);

                $grafica->update([
                    'current_balance' => 50000.00,
                    'is_defaulter' => false,
                ]);
            }

            // 3. Colegio Parroquial San José (pago exacto por transferencia, saldo en $0)
            $colegio = Customer::where('tenant_id', $tenant->id)
                ->where('name', 'Colegio Parroquial San José')
                ->first();

            if ($colegio) {
                CustomerAccountMovement::where('tenant_id', $tenant->id)
                    ->where('customer_id', $colegio->id)
                    ->delete();

                $order3 = CustomerOrder::where('tenant_id', $tenant->id)
                    ->where('customer_id', $colegio->id)
                    ->first();

                // Movimiento 1: Venta institucional
                CustomerAccountMovement::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $colegio->id,
                    'customer_order_id' => $order3?->id,
                    'created_by_user_id' => $userId,
                    'type' => 'charge',
                    'description' => 'Venta institucional - Pedido #'.($order3?->order_number ?? 'PED-1004'),
                    'amount' => 52300.00,
                    'balance_after' => 52300.00,
                    'due_date' => now()->addDays(18)->toDateString(),
                    'payment_method' => 'cuenta_corriente',
                    'reference_number' => $order3?->order_number ?? 'PED-1004',
                    'notes' => 'Factura B-0001-00001290 (Exento)',
                    'created_at' => now()->subDays(12),
                    'updated_at' => now()->subDays(12),
                ]);

                // Movimiento 2: Pago exacto por transferencia
                CustomerAccountMovement::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $colegio->id,
                    'customer_order_id' => null,
                    'created_by_user_id' => $userId,
                    'type' => 'payment',
                    'description' => 'Cobro / Abono total (transferencia) - Tesorería Parroquial',
                    'amount' => 52300.00,
                    'balance_after' => 0.00,
                    'due_date' => null,
                    'payment_method' => 'transferencia',
                    'reference_number' => 'TRF-SANJOSE-99',
                    'notes' => 'Cancelación total de saldo pendiente',
                    'created_at' => now()->subDays(5),
                    'updated_at' => now()->subDays(5),
                ]);

                $colegio->update([
                    'current_balance' => 0.00,
                    'is_defaulter' => false,
                ]);
            }

            // 4. Distribuidora Mayorista Norte (moroso, deuda vencida, ajuste de recargo por mora)
            $norte = Customer::where('tenant_id', $tenant->id)
                ->where('name', 'Distribuidora Mayorista Norte')
                ->first();

            if ($norte) {
                CustomerAccountMovement::where('tenant_id', $tenant->id)
                    ->where('customer_id', $norte->id)
                    ->delete();

                // Movimiento 1: Venta con vencimiento pasado (en mora)
                CustomerAccountMovement::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $norte->id,
                    'customer_order_id' => null,
                    'created_by_user_id' => $userId,
                    'type' => 'charge',
                    'description' => 'Venta a crédito - Pedido #PED-0988',
                    'amount' => 75000.00,
                    'balance_after' => 75000.00,
                    'due_date' => now()->subDays(30)->toDateString(),
                    'payment_method' => 'cuenta_corriente',
                    'reference_number' => 'PED-0988',
                    'notes' => 'Factura A-0001-00004100 vencida hace 30 días',
                    'created_at' => now()->subDays(60),
                    'updated_at' => now()->subDays(60),
                ]);

                // Movimiento 2: Ajuste de recargo por mora (Ajuste de Débito)
                CustomerAccountMovement::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $norte->id,
                    'customer_order_id' => null,
                    'created_by_user_id' => $userId,
                    'type' => 'adjustment',
                    'description' => 'Recargo por mora e intereses de financiación',
                    'amount' => 8500.00,
                    'balance_after' => 83500.00,
                    'due_date' => null,
                    'payment_method' => 'ajuste',
                    'reference_number' => 'AJU-MORA-01',
                    'notes' => 'Aplicación de intereses punitorios por mora superior a 30 días',
                    'created_at' => now()->subDays(15),
                    'updated_at' => now()->subDays(15),
                ]);

                $norte->update([
                    'current_balance' => 83500.00,
                    'is_defaulter' => true,
                ]);
            }
        }
    }
}
