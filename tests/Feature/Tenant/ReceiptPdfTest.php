<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderReceipt;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BaseSeeder::class);
    $this->tenant = Tenant::first();
    $this->adminUser = User::where('tenant_id', $this->tenant->id)->first();
    $this->defaultWarehouse = $this->tenant->warehouses()->where('is_default', true)->first();

    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Comercial Belgrano S.R.L.',
        'tax_id' => '30-71234567-8',
        'tax_condition' => 'responsable_inscripto',
        'address' => 'Av. Belgrano 1234',
        'phone' => '+54 341 4455667',
        'email' => 'compras@belgrano.com',
        'current_balance' => 0,
        'credit_limit' => 500000.00,
        'is_active' => true,
    ]);

    $this->receipt = Receipt::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by_user_id' => $this->adminUser->id,
        'receipt_number' => 'REC-0001',
        'amount' => 15000.00,
        'payment_method' => 'transferencia',
        'reference_number' => 'TRF-987654321',
        'notes' => 'Pago de facturas pendientes',
        'is_cancelled' => false,
    ]);

    $this->order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-0001',
        'customer_name' => $this->customer->name,
        'status' => 'entregado',
        'total_amount' => 15000.00,
        'paid_amount' => 15000.00,
        'debt_amount' => 0.00,
        'payment_status' => 'pagado',
    ]);

    CustomerOrderReceipt::create([
        'tenant_id' => $this->tenant->id,
        'receipt_id' => $this->receipt->id,
        'customer_order_id' => $this->order->id,
        'amount_applied' => 15000.00,
    ]);
});

test('authenticated tenant user can download receipt pdf', function () {
    Pdf::fake();

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.receipts.pdf', $this->receipt));

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.receipt'
            && $pdf->viewData['receipt']->id === $this->receipt->id
            && $pdf->viewData['receipt']->receipt_number === 'REC-0001';
    });
});

test('authenticated tenant user can preview receipt pdf inline', function () {
    Pdf::fake();

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.receipts.preview-pdf', $this->receipt));

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.receipt'
            && $pdf->viewData['receipt']->id === $this->receipt->id;
    });
});

test('cancelled receipt pdf is accessible with cancellation metadata', function () {
    Pdf::fake();

    $this->receipt->update([
        'is_cancelled' => true,
        'cancelled_at' => now(),
        'cancelled_by_user_id' => $this->adminUser->id,
        'cancellation_reason' => 'Cheque rechazado por el banco',
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.receipts.pdf', $this->receipt));

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.receipt'
            && $pdf->viewData['receipt']->is_cancelled === true;
    });
});

test('user from another tenant cannot access receipt pdf', function () {
    $otherTenant = Tenant::factory()->create(['is_active' => true]);
    $otherUser = User::factory()->forTenant($otherTenant)->create();

    $response = $this->actingAs($otherUser)
        ->get(route('tenant.payments.receipts.pdf', $this->receipt));

    $response->assertNotFound();
});

test('unauthenticated user is redirected when trying to download receipt pdf', function () {
    $response = $this->get(route('tenant.payments.receipts.pdf', $this->receipt));

    $response->assertRedirect(route('login'));
});
