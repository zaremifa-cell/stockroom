<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PriceImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Product::create(['sku' => 'KTL', 'name' => 'Kettle', 'price_cents' => 3490]);
        Product::create(['sku' => 'MUG', 'name' => 'Mug', 'price_cents' => 890]);
        Product::create(['sku' => 'PAN', 'name' => 'Pan', 'price_cents' => 2790]);
    }

    private function upload(string $csv)
    {
        return $this->post('/api/price-imports', ['file' => UploadedFile::fake()->createWithContent('prices.csv', $csv)], ['Accept' => 'application/json']);
    }

    private function price(string $sku): int
    {
        return Product::where('sku', $sku)->value('price_cents');
    }

    public function test_valid_rows_are_applied_and_invalid_rows_are_reported_by_line(): void
    {
        $this->upload("sku,price\nKTL,36.90\nMUG,9,90\nXYZ,10.00\nPAN,abc\n")
            ->assertCreated()
            ->assertJsonPath('rows_updated', 1)
            ->assertJsonPath('errors.0', ['line' => 3, 'message' => 'Too many columns. Put prices with a comma in quotes, e.g. "9,90".'])
            ->assertJsonPath('errors.1', ['line' => 4, 'message' => 'Unknown SKU XYZ.'])
            ->assertJsonPath('errors.2', ['line' => 5, 'message' => 'PAN: "abc" is not a valid price.']);

        $this->assertSame(3690, $this->price('KTL'));
        $this->assertSame(890, $this->price('MUG'));
        $this->assertDatabaseHas('stock_movements', ['kind' => 'price_change', 'note' => '€34.90 → €36.90']);
    }

    public function test_a_comma_decimal_inside_quotes_is_accepted(): void
    {
        $this->upload("sku,price\nMUG,\"9,90\"\n")->assertCreated()->assertJsonPath('rows_updated', 1);
        $this->assertSame(990, $this->price('MUG'));
    }

    public function test_the_same_file_twice_is_applied_once(): void
    {
        $csv = "sku,price\nKTL,36.90\n";
        $this->upload($csv)->assertCreated();
        Product::where('sku', 'KTL')->update(['price_cents' => 4000]); // someone edits the price afterwards

        $this->upload($csv)->assertOk()->assertJsonPath('already_imported', true);
        $this->assertSame(4000, $this->price('KTL'));
        $this->assertDatabaseCount('price_imports', 1);
    }

    public function test_windows_line_endings_count_as_the_same_file(): void
    {
        $this->upload("sku,price\nKTL,36.90\n")->assertCreated();
        $this->upload("sku,price\r\nKTL,36.90\r\n")->assertOk()->assertJsonPath('already_imported', true);
    }

    public function test_a_file_without_the_right_columns_changes_nothing(): void
    {
        $this->upload("code,cost\nKTL,1.00\n")
            ->assertCreated()
            ->assertJsonPath('errors.0.message', 'The first row must contain "sku" and "price" columns.');
        $this->assertSame(3490, $this->price('KTL'));
    }

    public function test_unchanged_prices_are_counted_but_not_logged(): void
    {
        $this->upload("sku,price\nKTL,34.90\n")->assertJsonPath('rows_unchanged', 1)->assertJsonPath('rows_updated', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
