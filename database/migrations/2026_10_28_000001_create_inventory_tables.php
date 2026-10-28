<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('country', 2);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('low_stock_threshold')->default(10);
            $table->timestamps();
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id']);
        });
        // Last line of defence: even a bug in application code can't make stock negative.
        DB::statement('ALTER TABLE stock_levels ADD CONSTRAINT stock_levels_quantity_non_negative CHECK (quantity >= 0)');

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('idempotency_key')->unique();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('customer');
            $table->unsignedInteger('total_cents');
            $table->timestamps();
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->timestamps();
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        Schema::create('price_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            // SHA-256 of the file contents: the same file can only ever be applied once.
            $table->string('checksum', 64)->unique();
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_unchanged')->default(0);
            $table->json('errors');
            $table->timestamps();
        });

        // Append-only history of every stock and price change.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('warehouse_id')->nullable()->constrained();
            $table->string('kind'); // order, transfer_out, transfer_in, adjustment, price_change
            $table->integer('quantity_change')->default(0);
            $table->string('note')->nullable();
            $table->nullableMorphs('source');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['stock_movements', 'price_imports', 'transfers', 'order_lines', 'orders', 'stock_levels', 'products', 'warehouses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
