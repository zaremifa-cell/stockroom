<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStock;
use App\Http\Controllers\Controller;
use App\Models\PriceImport;
use App\Services\ImportPrices;
use App\Services\PlaceOrder;
use App\Services\TransferStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function order(Request $request, PlaceOrder $placeOrder): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'customer' => ['required', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);
        $key = $this->idempotencyKey($request);

        try {
            [$order, $replayed] = $placeOrder->handle($data['warehouse_id'], $data['customer'], $data['lines'], $key);
        } catch (InsufficientStock $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->load('lines.product:id,sku,name');

        return response()->json([
            'reference' => $order->reference,
            'customer' => $order->customer,
            'total_cents' => $order->total_cents,
            'replayed' => $replayed,
            'lines' => $order->lines->map(fn ($l) => [
                'sku' => $l->product->sku, 'name' => $l->product->name,
                'quantity' => $l->quantity, 'unit_price_cents' => $l->unit_price_cents,
            ]),
        ], $replayed ? 200 : 201);
    }

    public function transfer(Request $request, TransferStock $transferStock): JsonResponse
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);
        $key = $this->idempotencyKey($request);

        try {
            [$transfer, $replayed] = $transferStock->handle(
                $data['from_warehouse_id'], $data['to_warehouse_id'], $data['product_id'], $data['quantity'], $key
            );
        } catch (InsufficientStock $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $transfer->id, 'quantity' => $transfer->quantity, 'replayed' => $replayed], $replayed ? 200 : 201);
    }

    public function imports(): JsonResponse
    {
        return response()->json(PriceImport::query()->latest('id')->limit(20)->get());
    }

    public function import(Request $request, ImportPrices $importPrices): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $file = $request->file('file');

        [$import, $alreadyImported] = $importPrices->handle($file->get(), $file->getClientOriginalName());

        return response()->json($import->toArray() + ['already_imported' => $alreadyImported], $alreadyImported ? 200 : 201);
    }

    private function idempotencyKey(Request $request): string
    {
        $key = (string) $request->header('Idempotency-Key');
        abort_if($key === '' || strlen($key) > 100, 400, 'Send an Idempotency-Key header (up to 100 characters).');

        return $key;
    }
}
