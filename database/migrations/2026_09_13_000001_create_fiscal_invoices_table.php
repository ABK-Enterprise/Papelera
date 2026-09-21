<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fiscal_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_order_id')->constrained('customer_orders')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->integer('cbte_tipo');
            $table->integer('punto_venta');
            $table->unsignedBigInteger('numero_comprobante');
            $table->string('cae', 14);
            $table->date('cae_due_date');
            $table->date('issue_date');
            $table->integer('doc_tipo');
            $table->string('doc_nro', 20);
            $table->integer('receptor_condicion_iva_id');
            $table->string('receptor_name', 255);
            $table->string('receptor_address', 255)->nullable();
            $table->integer('concepto')->default(1);
            $table->decimal('importe_total', 14, 2);
            $table->decimal('importe_neto', 14, 2);
            $table->decimal('importe_iva', 14, 2)->default(0.00);
            $table->decimal('importe_exento', 14, 2)->default(0.00);
            $table->decimal('importe_tributos', 14, 2)->default(0.00);
            $table->foreignId('associated_fiscal_invoice_id')->nullable()->constrained('fiscal_invoices')->restrictOnDelete();
            $table->text('qr_data');
            $table->json('arca_request_payload')->nullable();
            $table->json('arca_response_payload')->nullable();
            $table->string('status', 30)->default('aprobado');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'cbte_tipo', 'punto_venta', 'numero_comprobante'],
                'uq_fiscal_invoices_tenant_tipo_pto_nro'
            );

            $table->index(['tenant_id', 'customer_order_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_invoices');
    }
};
