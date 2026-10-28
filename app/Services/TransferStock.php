<?php

namespace App\Services;

use App\Models\Transfer;
use App\Models\Warehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Moves stock between warehouses in one transaction: units leave one warehouse and
 * arrive in the other together, or not at all. They can never disappear in between.
 */
class TransferStock
{
    public function __construct(private StockLedger $ledger) {}

    /** @return array{0: Transfer, 1: bool} the transfer and whether it was a replay */
    public function handle(int $fromId, int $toId, int $productId, int $quantity, string $idempotencyKey): array
    {
        if ($fromId === $toId) {
            throw new \InvalidArgumentException('Choose two different warehouses.');
        }

        if ($existing = Transfer::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return [$existing, true];
        }

        try {
            $transfer = DB::transaction(function () use ($fromId, $toId, $productId, $quantity, $idempotencyKey) {
                $levels = $this->ledger->lock([[$fromId, $productId], [$toId, $productId]]);
                $codes = Warehouse::query()->whereIn('id', [$fromId, $toId])->pluck('code', 'id');

                $transfer = Transfer::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    'from_warehouse_id' => $fromId,
                    'to_warehouse_id' => $toId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                ]);

                $this->ledger->move($levels["{$fromId}:{$productId}"], -$quantity, 'transfer_out', $transfer, "to {$codes[$toId]}");
                $this->ledger->move($levels["{$toId}:{$productId}"], $quantity, 'transfer_in', $transfer, "from {$codes[$fromId]}");

                return $transfer;
            });
        } catch (UniqueConstraintViolationException) {
            return [Transfer::query()->where('idempotency_key', $idempotencyKey)->firstOrFail(), true];
        }

        return [$transfer, false];
    }
}
