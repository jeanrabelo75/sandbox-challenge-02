# Challenge — Internal Product Import Service

This project implements a **CSV-based product import worker** that reads files from `/imports/products` and persists data to the database, handling:

- **Concurrency** (multiple instances running in parallel)
- **Idempotency** (file and product)
- **Failures** (row-level errors and fatal file errors)
- **Batch processing** (batch upsert)

---

## Challenge requirements covered

### Periodic worker

- The worker runs via: `php artisan products:import`
- In production, you can schedule this command every **10 minutes** (e.g., cron).

### Multiple parallel instances

- File “claiming” uses **PostgreSQL** with:
  - `FOR UPDATE SKIP LOCKED`
- This ensures **two instances never process the same file at the same time**.

### Safe and idempotent processing

#### File idempotency

- The `import_files` table controls the file lifecycle (`pending`, `processing`, `processed`, `error`).
- `file_path` is **unique**: the same file is not “discovered” twice.
- Files in `processed` **are not processed again**.

#### Product idempotency

- `products.external_id` is **unique**.
- Import uses `upsert` (insert/update) by `external_id`, therefore:
  - the same product may appear in different files without duplicating records.

### Failures

#### Errors

- Invalid rows are recorded in `import_errors`, and processing continues.

#### Fatal errors

- Example: **invalid header** → marks the file as `error` and saves `last_error`.

#### Reprocessing

- Files in `error` can be retried in future runs.
- To avoid an infinite loop with a permanently broken file, a **max attempts limit** was added (`MAX_ATTEMPTS`).

### Processing

- Parsing is streaming and `upsert` is executed in **batches** (`batchSize=1000`), suitable for hundreds of files.

### Observability

- Laravel logs (`Import started`, `Import finished`, `Import failed`)
- Control (`import_files`) and audit (`import_errors`) tables

---

## Architecture decisions

### Responsibilities

- **Worker**: orchestrates the flow (discover → claim → process → update status)
- **Repositories**:
  - `ImportFileRepository`: discover/claim/status
  - `ProductRepository`: batch upsert
  - `ImportErrorRepository`: records errors without duplication (`firstOrCreate`)
- **Parser**:
  - `CsvProductFileParser`: validates and maps CSV rows, yields batches

### `FOR UPDATE SKIP LOCKED`

This is a PostgreSQL technique for **concurrent consumption** of “tasks” (in this case, files to process), allowing N worker instances without conflicts.

### Prioritization

Without prioritization, a permanently invalid `error` file could be retried forever and **starve** new `pending` files.  
The claim query orders by status to prioritize **pending → error**.

---

## Structure (key files)

- `app/Console/Commands/ImportProductsCommand.php` — `products:import` command
- `app/Services/ProductImport/ProductImportWorker.php` — orchestrator
- `app/Services/ProductImport/CsvProductFileParser.php` — parser/validation/batches
- `app/DTOs/ProductImport/ParseResult.php` — parse result DTO
- `app/Repositories/ImportFileRepository.php` — discover/claim/status
- `app/Repositories/ProductRepository.php` — batch upsert
- `app/Repositories/ImportErrorRepository.php` — error logging

---

## How to run

### Prerequisites

- Docker and Docker Compose

### 1) Start containers

```bash
docker compose up -d db redis
```

### 2) Install dependencies

```bash
docker compose run --rm app composer install
```

### 3) Configure `.env`

```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=product_importer
DB_USERNAME=app
DB_PASSWORD=app
```

### 4) Run migrations

```bash
docker compose run --rm app php artisan migrate
```

### 5) Run the worker

```bash
docker compose run --rm app php artisan products:import
```

### 6) Run unit tests

```bash
docker compose run --rm app php artisan test --testsuite=Unit
```

---

## Execution examples

### `import_files`

![import_files table](docs/images/import_files.png)

### `import_errors`

![import_errors table](docs/images/import_errors.png)

### `products`

![products table](docs/images/products.png)

---
