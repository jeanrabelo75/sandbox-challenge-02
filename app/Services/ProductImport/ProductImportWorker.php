<?php

namespace App\Services\ProductImport;

use App\Repositories\ImportFileRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\Log;

class ProductImportWorker
{
    public function __construct(
        private readonly ImportFileRepository $importFileRepo,
        private readonly ProductRepository $productRepo,
        private readonly CsvProductFileParser $parser,
        private readonly string $dir = '/imports/products'
    ) {}

    public function run(int $maxFilesPerRun = 20): void
    {
        $this->importFileRepo->discoverCsvFiles($this->dir);

        $processed = 0;

        while ($processed < $maxFilesPerRun) {
            $file = $this->importFileRepo->claimNextFile();
            if (!$file) {
                return;
            }

            $processed++;

            try {
                Log::info('Import started', [
                    'file' => $file->file_path,
                    'import_file_id' => $file->id,
                ]);

                $parseResult = $this->parser->parse($file->file_path, $file->id, 1000);
                $upserted = 0;
                
                foreach ($parseResult->batches as $batch) {
                    $upserted += $this->productRepo->upsertMany($batch);
                }

                $this->importFileRepo->markProcessed($file, $parseResult->success, $parseResult->errors);

                Log::info('Import finished', [
                    'file' => $file->file_path,
                    'import_file_id' => $file->id,
                    'inserted_or_updated' => $upserted,
                    'rows_success' => $parseResult->success,
                    'rows_failed' => $parseResult->errors,
                ]);
            } catch (\Throwable $e) {
                $this->importFileRepo->markError($file, $e);

                Log::error('Import failed', [
                    'file' => $file->file_path,
                    'import_file_id' => $file->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
