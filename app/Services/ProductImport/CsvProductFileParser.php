<?php

namespace App\Services\ProductImport;

use App\DTOs\ProductImport\ParseResult;
use App\Repositories\ImportErrorRepository;

class CsvProductFileParser
{
    private const REQUIRED_HEADER = ['external_id', 'name', 'price', 'stock', 'active'];

    public function __construct(
        private readonly ImportErrorRepository $errorRepo
    ) {}

    public function parse(string $path, int $importFileId, int $batchSize = 1000): ParseResult
    {
        $result = new ParseResult(batches: (function () { if (false) yield []; })());
        $result->batches = $this->generateBatches($path, $importFileId, $batchSize, $result);

        return $result;
    }

    private function generateBatches(
        string $path,
        int $importFileId,
        int $batchSize,
        ParseResult $result
    ): \Generator {
        if (!is_readable($path)) {
            throw new \RuntimeException("File not readable: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open file: {$path}");
        }

        try {
            $headerRow = fgetcsv($handle, 0, ';', '"', '\\');
            $headerRow = $headerRow ? array_map('trim', $headerRow) : [];
            $rawHeaderLine = implode(';', $headerRow);

            if ($headerRow !== self::REQUIRED_HEADER) {
                $this->errorRepo->logHeaderError(
                    $importFileId,
                    $rawHeaderLine,
                    implode(';', self::REQUIRED_HEADER)
                );

                throw new \RuntimeException('Invalid CSV header');
            }

            $lineNumber = 1;
            $batch = [];

            while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
                $lineNumber++;

                if ($row === [null] || $row === []) {
                    continue;
                }

                $rawLine = implode(';', array_map(fn($v) => $v === null ? '' : (string)$v, $row));

                try {
                    $batch[] = $this->validateAndMapRow($row);
                    $result->success++;

                    if (count($batch) >= $batchSize) {
                        yield $batch;
                        $batch = [];
                    }
                } catch (\Throwable $e) {
                    $result->errors++;

                    $this->errorRepo->logLineError(
                        $importFileId,
                        $lineNumber,
                        $row[0] ?? null,
                        $e->getMessage(),
                        $rawLine
                    );
                }
            }

            if (!empty($batch)) {
                yield $batch;
            }
        } finally {
            fclose($handle);
        }
    }

    private function validateAndMapRow(array $row): array
    {
        if (count($row) !== 5) {
            throw new \InvalidArgumentException('Invalid column count');
        }

        [$externalId, $name, $price, $stock, $active] = array_map('trim', $row);

        if ($externalId === '') throw new \InvalidArgumentException('external_id is required');
        if ($name === '') throw new \InvalidArgumentException('name is required');

        if (!is_numeric($price) || (float)$price <= 0) {
            throw new \InvalidArgumentException('price must be a number > 0');
        }

        if (!ctype_digit((string)$stock)) {
            throw new \InvalidArgumentException('stock must be an integer >= 0');
        }

        $activeLower = strtolower((string)$active);
        if (!in_array($activeLower, ['true', 'false'], true)) {
            throw new \InvalidArgumentException('active must be true|false');
        }

        return [
            'external_id' => (string) $externalId,
            'name' => (string) $name,
            'price' => (float) $price,
            'stock' => (int) $stock,
            'active' => $activeLower === 'true',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
