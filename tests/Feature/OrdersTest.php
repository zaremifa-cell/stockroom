<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $plovdiv;

    private Product $kettle;

    private Product $mug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plovdiv = Warehouse::create(['code' => 'PDV', 'name' => 'Plovdiv', 'country' => 'BG']);
        $this->kettle = Product::create(['sku' => 'KTL', 'name' => 'Kettle', 'price_cents' => 3490]);
        $this->mug = Product::create(['sku' => 'MUG', 'name' => 'Mug', 'price_cents' => 890]);
        $this->stock($this->kettle, 5);
        $this->stock($this->mug, 2);
    }

    private function stock(Product $product, int $quantity): void
    {
        StockLevel::create(['warehouse_id' => $this->plovdiv->id, 'product_id' => $product->id, 'quantity' => $quantity]);
    }

    private function quantity(Product $product): int
    {
        return StockLevel::where(['warehouse_id' => $this->plovdiv->id, 'product_id' => $product->id])->value('quantity');
    }

    private function order(array $lines, string $key = 'key-1')
    {
        return $this->postJson('/api/orders', ['warehouse_id' => $this->plovdiv->id, 'customer' => 'Shop', 'lines' => $lines], ['Idempotency-Key' => $key]);
    }

    public function test_an_order_takes_stock_and_records_history(): void
    {
        $this->order([['product_id' => $this->kettle->id, 'quantity' => 2]])
            ->assertCreated()
            ->assertJsonPath('total_cents', 6980);

        $this->assertSame(3, $this->quantity($this->kettle));
        $this->assertDatabaseHas('stock_movements', ['product_id' => $this->kettle->id, 'kind' => 'order', 'quantity_change' => -2]);
    }

    public function test_a_shortage_on_one_line_takes_nothing_from_any_line(): void
    {
        $this->order([
            ['product_id' => $this->kettle->id, 'quantity' => 1],
            ['product_id' => $this->mug->id, 'quantity' => 3],
        ])->assertStatus(422)->assertJsonPath('message', 'Not enough MUG: 2 in stock, 3 requested.');

        $this->assertSame(5, $this->quantity($this->kettle));
        $this->assertSame(2, $this->quantity($this->mug));
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_two_lines_for_the_same_product_are_checked_together(): void
    {
        $this->order([
            ['product_id' => $this->mug->id, 'quantity' => 2],
            ['product_id' => $this->mug->id, 'quantity' => 1],
        ])->assertStatus(422);

        $this->assertSame(2, $this->quantity($this->mug));
    }

    public function test_the_same_idempotency_key_places_the_order_once(): void
    {
        $first = $this->order([['product_id' => $this->kettle->id, 'quantity' => 2]])->assertCreated();
        $second = $this->order([['product_id' => $this->kettle->id, 'quantity' => 2]])->assertOk()->assertJsonPath('replayed', true);

        $this->assertSame($first->json('reference'), $second->json('reference'));
        $this->assertSame(3, $this->quantity($this->kettle));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_an_order_without_an_idempotency_key_is_rejected(): void
    {
        $this->postJson('/api/orders', ['warehouse_id' => $this->plovdiv->id, 'customer' => 'Shop', 'lines' => [['product_id' => $this->kettle->id, 'quantity' => 1]]])
            ->assertStatus(400);
        $this->assertSame(5, $this->quantity($this->kettle));
    }

    public function test_invalid_input_is_explained(): void
    {
        $this->order([['product_id' => 9999, 'quantity' => 0]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.product_id', 'lines.0.quantity']);
    }

    public function test_the_database_refuses_negative_stock_even_without_the_application(): void
    {
        $this->expectException(QueryException::class);
        StockLevel::where('product_id', $this->mug->id)->update(['quantity' => -1]);
    }
}
