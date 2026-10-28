<?php

namespace App\Services;

use App\Models\PriceImport;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Updates prices from a CSV with "sku" and "price" columns (price in euros, "12.50" or "12,50").
 * Files saved by Excel work too: the UTF-8 byte order mark is ignored, and semicolon-separated
 * files (Excel's default where the comma is the decimal separator) are recognised from the header.
 *
 * - The same file uploaded twice is applied once: it is identified by a SHA-256 of its contents.
 * - Valid rows are applied together in one transaction; each invalid row is reported with its
 *   line number and reason instead of failing silently.
 */
class ImportPrices
{
    /** @return array{0: PriceImport, 1: bool} the import and whether this file was already imported */
    public function handle(string $contents, string $filename): array
    {
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        $checksum = hash('sha256', $contents);

        if ($existing = PriceImport::query()->where('checksum', $checksum)->first()) {
            return [$existing, true];
        }

        [$rows, $errors] = $this->parse($contents);

        try {
            $import = DB::transaction(function () use ($rows, $errors, $filename, $checksum) {
                $import = PriceImport::query()->create([
                    'filename' => $filename, 'checksum' => $checksum, 'errors' => $errors,
                ]);

                $products = Product::query()->whereIn('sku', array_column($rows, 'sku'))->lockForUpdate()->get()->keyBy('sku');
                $updated = $unchanged = 0;

                foreach ($rows as $row) {
                    $product = $products[$row['sku']] ?? null;
                    if (! $product) {
                        $errors[] = ['line' => $row['line'], 'message' => "Unknown SKU {$row['sku']}."];

                        continue;
                    }
                    if ($product->price_cents === $row['price_cents']) {
                        $unchanged++;

                        continue;
                    }

                    StockMovement::query()->create([
                        'product_id' => $product->id,
                        'kind' => 'price_change',
                        'note' => self::euros($product->price_cents).' → '.self::euros($row['price_cents']),
                        'source_type' => $import->getMorphClass(),
                        'source_id' => $import->id,
                    ]);
                    $product->update(['price_cents' => $row['price_cents']]);
                    $updated++;
                }

                usort($errors, fn ($a, $b) => $a['line'] <=> $b['line']);
                $import->update(['rows_updated' => $updated, 'rows_unchanged' => $unchanged, 'errors' => $errors]);

                return $import;
            });
        } catch (UniqueConstraintViolationException) {
            return [PriceImport::query()->where('checksum', $checksum)->firstOrFail(), true];
        }

        return [$import, false];
    }

    /** @return array{0: array<int, array{line:int, sku:string, price_cents:int}>, 1: array<int, array{line:int, message:string}>} */
    public function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = array_values(array_filter(explode("\n", trim($contents)), fn ($l) => trim($l) !== ''));
        if ($lines === []) {
            return [[], [['line' => 1, 'message' => 'The file is empty.']]];
        }

        $separator = str_contains($lines[0], ';') && ! str_contains($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv($lines[0], $separator, escape: ''));
        $skuCol = array_search('sku', $header, true);
        $priceCol = array_search('price', $header, true);
        if ($skuCol === false || $priceCol === false) {
            return [[], [['line' => 1, 'message' => 'The first row must contain "sku" and "price" columns.']]];
        }

        $rows = $errors = $seen = [];
        foreach (array_slice($lines, 1) as $i => $raw) {
            $lineNo = $i + 2;
            $cells = str_getcsv($raw, $separator, escape: '');
            $sku = trim($cells[$skuCol] ?? '');
            $price = trim($cells[$priceCol] ?? '');

            if (count($cells) > count($header)) {
                // Usually an unquoted "9,90": read naively it would silently become €9.00.
                $errors[] = ['line' => $lineNo, 'message' => 'Too many columns. Put prices with a comma in quotes, e.g. "9,90".'];
            } elseif ($sku === '') {
                $errors[] = ['line' => $lineNo, 'message' => 'Missing SKU.'];
            } elseif (isset($seen[$sku])) {
                $errors[] = ['line' => $lineNo, 'message' => "{$sku} appears twice (first on line {$seen[$sku]})."];
            } elseif (($cents = self::parseEuros($price)) === null) {
                $errors[] = ['line' => $lineNo, 'message' => "{$sku}: \"{$price}\" is not a valid price."];
            } else {
                $seen[$sku] = $lineNo;
                $rows[] = ['line' => $lineNo, 'sku' => $sku, 'price_cents' => $cents];
            }
        }

        return [$rows, $errors];
    }

    /** "12.5", "12,50" or "12" → cents. No floats, so 19.99 never becomes 1998. */
    public static function parseEuros(string $value): ?int
    {
        if (! preg_match('/^(\d{1,6})(?:[.,](\d{1,2}))?$/', $value, $m)) {
            return null;
        }
        $cents = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');

        return $cents > 0 ? $cents : null;
    }

    public static function euros(int $cents): string
    {
        return '€'.number_format($cents / 100, 2);
    }
}
