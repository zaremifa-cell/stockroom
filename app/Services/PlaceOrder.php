<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Takes stock out of one warehouse for an order, exactly once per idempotency key.
 * Either every line is reserved or nothing is: a shortage on line 3 rolls back lines 1 and 2.
 */
class PlaceOrder
{
    public function __construct(private StockLedger $ledger) {}

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $lines
     * @return array{0: Order, 1: bool} the order and whether it was a replay
     */
    public function handle(int $warehouseId, string $customer, array $lines, string $idempotencyKey): array
    {
        if ($existing = Order::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return [$existing, true];
        }

        // Two lines for the same product become one, so the stock check sees the real total.
        $quantities = [];
        foreach ($lines as $line) {
            $quantities[$line['product_id']] = ($quantities[$line['product_id']] ?? 0) + $line['quantity'];
        }

        try {
            $order = DB::transaction(function () use ($warehouseId, $customer, $quantities, $idempotencyKey) {
                $products = Product::query()->whereIn('id', array_keys($quantities))->get()->keyBy('id');
                $levels = $this->ledger->lock(array_map(fn ($id) => [$warehouseId, $id], array_keys($quantities)));

                $order = Order::query()->create([
                    'reference' => 'ORD-'.Str::upper(Str::random(8)),
                    'idempotency_key' => $idempotencyKey,
                    'warehouse_id' => $warehouseId,
                    'customer' => $customer,
                    'total_cents' => 0,
                ]);

                $total = 0;
                foreach ($quantities as $productId => $quantity) {
                    $product = $products[$productId];
                    $this->ledger->move($levels["{$warehouseId}:{$productId}"], -$quantity, 'order', $order, $order->reference);
                    $order->lines()->create([
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'unit_price_cents' => $product->price_cents,
                    ]);
                    $total += $product->price_cents * $quantity;
                }

                $order->update(['total_cents' => $total]);

                return $order;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same key committed first; return its order.
            return [Order::query()->where('idempotency_key', $idempotencyKey)->firstOrFail(), true];
        }

        return [$order, false];
    }
}
