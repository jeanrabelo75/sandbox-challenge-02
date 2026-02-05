<?php

namespace App\Repositories;

use App\Models\ImportFile;
use Illuminate\Support\Facades\DB;

class ImportFileRepository
{
    private const MAX_ATTEMPTS = 5;

    public function discoverCsvFiles(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob(rtrim($dir, '/') . '/*.csv') ?: [];

        foreach ($files as $path) {
            $stat = @stat($path);
            if (!$stat) continue;

            DB::table('import_files')->insertOrIgnore([
                'file_path' => $path,
                'file_size' => (int) $stat['size'],
                'file_mtime' => (int) $stat['mtime'],
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function claimNextFile(): ?ImportFile
    {
        return DB::transaction(function () {
            $row = DB::selectOne("
                SELECT *
                FROM import_files
                WHERE status IN ('pending', 'error')
                  AND attempts < ?
                ORDER BY
                  CASE WHEN status = 'pending' THEN 0 ELSE 1 END,
                  id
                FOR UPDATE SKIP LOCKED
                LIMIT 1
            ", [self::MAX_ATTEMPTS]);

            if (!$row) {
                return null;
            }

            DB::update("
                UPDATE import_files
                SET status = 'processing',
                    locked_at = NOW(),
                    attempts = attempts + 1,
                    updated_at = NOW()
                WHERE id = ?
            ", [$row->id]);

            return ImportFile::find((int) $row->id);
        }, 3);
    }

    public function markProcessed(ImportFile $file, int $rowsSuccess, int $rowsFailed): void
    {
        $file->update([
            'status' => 'processed',
            'processed_at' => now(),
            'last_error' => null,
            'rows_success' => $rowsSuccess,
            'rows_failed' => $rowsFailed,
        ]);
    }

    public function markError(ImportFile $file, \Throwable $e): void
    {
        $file->update([
            'status' => 'error',
            'last_error' => $e->getMessage(),
        ]);
    }
}
