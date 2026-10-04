<?php

namespace App\Services;

use App\Events\Tenant\ProductPriceChanged;
use App\Models\CustomerOrder;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductPriceUpdateService
{
    /**
     * Update product catalog price and flag affected pending orders.
     *
     * @return array<int> IDs of affected customer orders
     */
    public function updatePrice(Product $product, float $newPrice, ?float $previousPrice = null, ?int $excludeOrderId = null): array
    {
        if ($newPrice <= 0 || $newPrice > 100000000) {
            throw new \InvalidArgumentException('El precio del producto debe ser mayor a 0 y no puede superar los $100.000.000.');
        }

        $oldPrice = $previousPrice !== null ? $previousPrice : (float) $product->price;

        if (abs($oldPrice - $newPrice) < 0.0001) {
            return [];
        }

        return DB::transaction(function () use ($product, $oldPrice, $newPrice, $excludeOrderId) {
            if (abs((float) $product->price - $newPrice) >= 0.0001) {
                $product->update(['price' => $newPrice]);
            }

            // Query affected non-terminal, non-invoiced orders
            $query = CustomerOrder::where('tenant_id', $product->tenant_id)
                ->whereNotIn('status', ['entregado', 'cancelado'])
                ->where('fiscal_status', '!=', 'facturado')
                ->whereHas('items', function ($q) use ($product) {
                    $q->where('product_id', $product->id);
                });

            if ($excludeOrderId !== null) {
                $query->where('id', '!=', $excludeOrderId);
            }

            $affectedOrderIds = $query->pluck('id')->all();

            if (! empty($affectedOrderIds)) {
                CustomerOrder::whereIn('id', $affectedOrderIds)->update([
                    'price_review_pending' => true,
                ]);
            }

            event(new ProductPriceChanged($product, $oldPrice, $newPrice, $affectedOrderIds));

            return $affectedOrderIds;
        });
    }
}
