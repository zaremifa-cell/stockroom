<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransfersTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $sofia;

    private Warehouse $plovdiv;

    private Product $lamp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sofia = Warehouse::create(['code' => 'SOF', 'name' => 'Sofia', 'country' => 'BG']);
        $this->plovdiv = Warehouse::create(['code' => 'PDV', 'name' => 'Plovdiv', 'country' => 'BG']);
        $this->lamp = Product::create(['sku' => 'LMP', 'name' => 'Lamp', 'price_cents' => 4290]);
        StockLevel::create(['warehouse_id' => $this->sofia->id, 'product_id' => $this->lamp->id, 'quantity' => 20]);
    }

    private function qty(Warehouse $w): ?int
    {
        return StockLevel::where(['warehouse_id' => $w->id, 'product_id' => $this->lamp->id])->value('quantity');
    }

    private function transfer(int $quantity, string $key = 't-1', ?Warehouse $to = null)
    {
        return $this->postJson('/api/transfers', [
            'from_warehouse_id' => $this->sofia->id, 'to_warehouse_id' => ($to ?? $this->plovdiv)->id,
            'product_id' => $this->lamp->id, 'quantity' => $quantity,
        ], ['Idempotency-Key' => $key]);
    }

    public function test_stock_leaves_one_warehouse_and_arrives_in_the_other(): void
    {
        $this->transfer(8)->assertCreated();

        $this->assertSame(12, $this->qty($this->sofia));
        $this->assertSame(8, $this->qty($this->plovdiv));
        $this->assertDatabaseHas('stock_movements', ['kind' => 'transfer_out', 'quantity_change' => -8, 'note' => 'to PDV']);
        $this->assertDatabaseHas('stock_movements', ['kind' => 'transfer_in', 'quantity_change' => 8, 'note' => 'from SOF']);
    }

    public function test_a_transfer_larger_than_the_stock_changes_nothing(): void
    {
        $this->transfer(21)->assertStatus(422);

        $this->assertSame(20, $this->qty($this->sofia));
        $this->assertSame(0, (int) $this->qty($this->plovdiv));
        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_a_retried_transfer_moves_stock_once(): void
    {
        $this->transfer(5)->assertCreated();
        $this->transfer(5)->assertOk()->assertJsonPath('replayed', true);

        $this->assertSame(15, $this->qty($this->sofia));
        $this->assertSame(5, $this->qty($this->plovdiv));
    }

    public function test_a_warehouse_cannot_transfer_to_itself(): void
    {
        $this->transfer(1, to: $this->sofia)->assertStatus(422)->assertJsonValidationErrors('to_warehouse_id');
    }
}
