<?php

namespace App\Repositories;

use App\Models\ImportError;

class ImportErrorRepository
{
    public function logHeaderError(int $importFileId, string $rawHeaderLine, string $expectedHeader): void
    {
        $message = 'Invalid header. Expected: ' . $expectedHeader;

        ImportError::firstOrCreate(
            [
                'import_file_id' => $importFileId,
                'line_number' => 1,
                'message' => $message,
            ],
            [
                'external_id' => null,
                'raw_line' => $rawHeaderLine,
            ]
        );
    }

    public function logLineError(
        int $importFileId,
        int $lineNumber,
        ?string $externalId,
        string $message,
        string $rawLine
    ): void {
        ImportError::firstOrCreate(
            [
                'import_file_id' => $importFileId,
                'line_number' => $lineNumber,
                'message' => $message,
            ],
            [
                'external_id' => $externalId,
                'raw_line' => $rawLine,
            ]
        );
    }
}
