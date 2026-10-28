<?php

namespace App\Services;

use App\Exceptions\InsufficientStock;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only place that changes stock quantities. Must be called inside a transaction.
 *
 * Rows are locked with SELECT ... FOR UPDATE, always in id order, so two orders for the
 * last unit are serialised (one waits, then sees 0) and two transfers in opposite
 * directions can't deadlock each other.
 */
class StockLedger
{
    /**
     * @param  array<int, array{0:int, 1:int}>  $pairs  [warehouse_id, product_id]
     * @return Collection<string, StockLevel> keyed "warehouseId:productId"
     */
    public function lock(array $pairs): Collection
    {
        $this->assertInTransaction();

        // Make sure a row exists for every pair so there is something to lock.
        StockLevel::query()->insertOrIgnore(array_map(fn ($p) => [
            'warehouse_id' => $p[0], 'product_id' => $p[1], 'quantity' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ], $pairs));

        return StockLevel::query()
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as [$warehouseId, $productId]) {
                    $q->orWhere(fn ($w) => $w->where('warehouse_id', $warehouseId)->where('product_id', $productId));
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (StockLevel $l) => "{$l->warehouse_id}:{$l->product_id}");
    }

    public function move(StockLevel $level, int $change, string $kind, Model $source, ?string $note = null): void
    {
        $this->assertInTransaction();

        if ($level->quantity + $change < 0) {
            $sku = Product::query()->whereKey($level->product_id)->value('sku');
            throw new InsufficientStock($sku, $level->quantity, -$change);
        }

        $level->quantity += $change;
        $level->save();

        StockMovement::query()->create([
            'product_id' => $level->product_id,
            'warehouse_id' => $level->warehouse_id,
            'kind' => $kind,
            'quantity_change' => $change,
            'note' => $note,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
        ]);
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('StockLedger must be used inside a database transaction.');
        }
    }
}
