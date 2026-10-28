<?php

namespace Tests\Unit;

use App\Services\ImportPrices;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PriceParsingTest extends TestCase
{
    public static function prices(): array
    {
        return [
            ['12', 1200], ['12.5', 1250], ['12,50', 1250], ['19.99', 1999], ['0.01', 1],
            ['0', null], ['-5', null], ['12.345', null], ['1 000', null], ['abc', null], ['', null], ['1234567', null],
        ];
    }

    #[DataProvider('prices')]
    public function test_euro_strings_become_exact_cents(string $input, ?int $cents): void
    {
        $this->assertSame($cents, ImportPrices::parseEuros($input));
    }

    public function test_duplicate_skus_in_one_file_are_reported(): void
    {
        [$rows, $errors] = (new ImportPrices)->parse("sku,price\nKTL,1.00\nKTL,2.00\n");

        $this->assertCount(1, $rows);
        $this->assertSame([['line' => 3, 'message' => 'KTL appears twice (first on line 2).']], $errors);
    }

    public function test_files_saved_by_excel_are_read(): void
    {
        // UTF-8 with a byte order mark, and semicolons with comma decimals (European Excel).
        [$rows, $errors] = (new ImportPrices)->parse("\xEF\xBB\xBFsku;price\r\nKTL;12,50\r\nPAN;9\r\n");

        $this->assertSame([], $errors);
        $this->assertSame([1250, 900], array_column($rows, 'price_cents'));
    }
}
