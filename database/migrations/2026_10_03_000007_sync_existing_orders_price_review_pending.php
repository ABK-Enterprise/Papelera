<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reconcile and flag existing non-terminal, non-invoiced orders that contain products
        // whose current catalog price differs from the order item unit_price.
        DB::table('customer_orders')
            ->whereNotIn('status', ['entregado', 'cancelado'])
            ->where('fiscal_status', '!=', 'facturado')
            ->where('price_review_pending', false)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('customer_order_items')
                    ->join('products', 'customer_order_items.product_id', '=', 'products.id')
                    ->whereColumn('customer_order_items.customer_order_id', 'customer_orders.id')
                    ->whereRaw('ABS(customer_order_items.unit_price - products.price) >= 0.01');
            })
            ->update([
                'price_review_pending' => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data reconciliation does not require reversal
    }
};
