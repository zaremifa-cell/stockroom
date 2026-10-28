# Stockroom — inventory across warehouses

A small inventory system for a company that sells physical products from several warehouses,
built with **PHP 8.5, Laravel 13, PostgreSQL and Vue 3**.

Staff see stock per warehouse, place orders, move stock between warehouses and update prices
from a CSV file. Every change is written to an append-only history.

The interesting part is that the numbers stay right when several people act at the same time,
when a request is retried, or when a file is uploaded twice.

## What it does

- **Stock** — every product's quantity in Plovdiv, Sofia and London, with low-stock warnings,
  search, total units and stock value.
- **Orders** — take several products out of one warehouse in a single order.
- **Transfers** — move units from one warehouse to another.
- **Price import** — upload a CSV with `sku` and `price` columns; see exactly which rows were
  applied and why the others were skipped.
- **History** — every order, transfer and price change, newest first.

## Correctness

| Situation | What happens |
|---|---|
| Two people order the last unit at the same moment | Stock rows are locked with `SELECT … FOR UPDATE` inside a transaction. The second order waits, sees 0 and is refused. Verified with 10 parallel requests against 3 units: 3 succeed, 7 are refused, stock ends at 0. |
| A bug tries to make stock negative anyway | A `CHECK (quantity >= 0)` constraint in PostgreSQL rejects it. |
| Line 3 of an order can't be filled | The whole order rolls back; lines 1 and 2 are not taken. |
| A request is retried or "Place order" is clicked twice | `Idempotency-Key` header: the same key returns the original order or transfer. A unique index covers the race between two identical requests. |
| Two transfers in opposite directions at once | Rows are always locked in id order, so they can't deadlock. |
| A transfer fails halfway | Both sides change in one transaction; units can't disappear between warehouses. |
| The same price file is uploaded twice | Files are identified by a SHA-256 of their contents (line endings normalised) and applied once. |
| `9,90` written without quotes in a CSV | Reported as "too many columns" instead of silently becoming €9.00. Prices are parsed to integer cents, never floats. |

## Code map

```
app/Services/StockLedger.php     the only code that changes quantities: locking + history
app/Services/PlaceOrder.php      orders, all-or-nothing, idempotent
app/Services/TransferStock.php   transfers between warehouses
app/Services/ImportPrices.php    CSV parsing and price updates
app/Http/Controllers/Api/        thin JSON controllers with request validation
resources/js/                    Vue 3 single-page interface
tests/                           PHPUnit feature and unit tests
docker/, Dockerfile, docker-compose.yml   nginx + PHP-FPM + PostgreSQL
```

## Run it locally

Requires PHP 8.5 with `pdo_pgsql`, Composer, Node 22 and PostgreSQL.

```bash
composer install
cp .env.example .env && php artisan key:generate
createdb stockroom && createdb stockroom_test
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

## Run it with Docker (nginx + PHP-FPM + PostgreSQL)

```bash
APP_KEY=$(php artisan key:generate --show) docker compose up --build
# http://localhost:8080
```

## Tests

```bash
php artisan test
```

30 tests: orders, shortages and rollback, idempotency, transfers, the database constraint,
CSV parsing and price imports. GitHub Actions runs them against PostgreSQL, checks code style
with Laravel Pint and builds the frontend on every push.

## API

| Method | Path | |
|---|---|---|
| GET | `/api/warehouses` | Warehouses |
| GET | `/api/products` | Products with stock per warehouse |
| GET | `/api/movements` | Latest 100 history entries |
| POST | `/api/orders` | Place an order (`Idempotency-Key` header required) |
| POST | `/api/transfers` | Move stock between warehouses (`Idempotency-Key` header required) |
| GET | `/api/price-imports` | Recent price imports |
| POST | `/api/price-imports` | Upload a CSV (`file`) |

## Limits

There is no login: it is a demo of the inventory logic, not a finished product. In a real
deployment the API would sit behind authentication with per-role permissions, and large imports
would run as queued jobs.
