<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileOrderPriceReviewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:reconcile-price-reviews';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile and flag non-terminal customer orders with price discrepancies compared to catalog';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Searching for orders with price discrepancies...');

        $updatedCount = DB::table('customer_orders')
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

        $this->info("Reconciliation complete. {$updatedCount} order(s) marked for price review.");

        return Command::SUCCESS;
    }
}
