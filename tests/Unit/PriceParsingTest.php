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
}
