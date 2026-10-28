<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    public function warehouses(): JsonResponse
    {
        return response()->json(Warehouse::query()->orderBy('id')->get(['id', 'code', 'name', 'country']));
    }

    /** Every product with its quantity in each warehouse — one query for products, one for stock. */
    public function products(): JsonResponse
    {
        $products = Product::query()->with('stockLevels:id,product_id,warehouse_id,quantity')->orderBy('sku')->get();

        return response()->json($products->map(fn (Product $p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'name' => $p->name,
            'price_cents' => $p->price_cents,
            'low_stock_threshold' => $p->low_stock_threshold,
            'stock' => $p->stockLevels->pluck('quantity', 'warehouse_id'),
            'total' => $p->stockLevels->sum('quantity'),
        ]));
    }

    public function movements(): JsonResponse
    {
        $movements = StockMovement::query()->with(['product:id,sku,name', 'warehouse:id,code'])
            ->latest('id')->limit(100)->get();

        return response()->json($movements->map(fn (StockMovement $m) => [
            'id' => $m->id,
            'kind' => $m->kind,
            'sku' => $m->product->sku,
            'product' => $m->product->name,
            'warehouse' => $m->warehouse?->code,
            'quantity_change' => $m->quantity_change,
            'note' => $m->note,
            'created_at' => $m->created_at,
        ]));
    }
}
