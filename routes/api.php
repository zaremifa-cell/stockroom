<?php

use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\StockController;
use Illuminate\Support\Facades\Route;

Route::get('warehouses', [InventoryController::class, 'warehouses']);
Route::get('products', [InventoryController::class, 'products']);
Route::get('movements', [InventoryController::class, 'movements']);

Route::middleware('throttle:60,1')->group(function () {
    Route::post('orders', [StockController::class, 'order']);
    Route::post('transfers', [StockController::class, 'transfer']);
    Route::get('price-imports', [StockController::class, 'imports']);
    Route::post('price-imports', [StockController::class, 'import']);
});
