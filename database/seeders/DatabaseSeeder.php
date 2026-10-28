<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/** Demo data: three warehouses and a small catalogue, with a few items already running low. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = collect([
            ['code' => 'PDV', 'name' => 'Plovdiv', 'country' => 'BG'],
            ['code' => 'SOF', 'name' => 'Sofia', 'country' => 'BG'],
            ['code' => 'LON', 'name' => 'London', 'country' => 'GB'],
        ])->map(fn ($w) => Warehouse::query()->updateOrCreate(['code' => $w['code']], $w));

        $catalogue = [
            // sku, name, price, [PDV, SOF, LON]
            ['KTL-STL-17', 'Steel kettle 1.7 L', 3490, [42, 18, 7]],
            ['BLN-PRO-900', 'Pro blender 900 W', 8990, [12, 4, 20]],
            ['PAN-CER-28', 'Ceramic pan 28 cm', 2790, [65, 30, 15]],
            ['KNF-SET-5', 'Chef knife set, 5 pcs', 5990, [9, 14, 3]],
            ['MUG-ENM-04', 'Enamel mug 400 ml', 890, [140, 75, 60]],
            ['TWL-COT-L', 'Cotton towel, large', 1490, [80, 6, 40]],
            ['LMP-DSK-LED', 'LED desk lamp', 4290, [21, 11, 2]],
            ['BTL-GLS-1L', 'Glass bottle 1 L', 1190, [55, 48, 33]],
        ];

        foreach ($catalogue as [$sku, $name, $price, $stock]) {
            $product = Product::query()->updateOrCreate(['sku' => $sku], ['name' => $name, 'price_cents' => $price]);
            foreach ($warehouses as $i => $warehouse) {
                StockLevel::query()->updateOrCreate(
                    ['warehouse_id' => $warehouse->id, 'product_id' => $product->id],
                    ['quantity' => $stock[$i]],
                );
            }
        }
    }
}
