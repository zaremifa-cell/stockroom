<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoPrepareTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_database_is_seeded(): void
    {
        $this->artisan('demo:prepare')->assertSuccessful();
        $this->assertSame(3, Warehouse::count());
    }

    public function test_fresh_demo_data_is_left_alone(): void
    {
        config(['app.demo_reset_hours' => 24]);
        $this->artisan('demo:prepare');
        $warehouse = Warehouse::first();
        Order::create(['reference' => 'ORD-1', 'idempotency_key' => 'k', 'warehouse_id' => $warehouse->id, 'customer' => 'x', 'total_cents' => 0]);

        $this->artisan('demo:prepare');
        $this->assertSame(1, Order::count());
    }

    public function test_stale_demo_data_is_reset(): void
    {
        config(['app.demo_reset_hours' => 24]);
        $this->artisan('demo:prepare');
        Warehouse::query()->update(['created_at' => now()->subHours(25)]);
        Order::create(['reference' => 'ORD-1', 'idempotency_key' => 'k', 'warehouse_id' => Warehouse::first()->id, 'customer' => 'x', 'total_cents' => 0]);

        $this->artisan('demo:prepare')->expectsOutputToContain('has been reset');
        $this->assertSame(0, Order::count());
        $this->assertSame(3, Warehouse::count());
    }
}
