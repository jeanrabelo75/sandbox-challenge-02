<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProductImport\ProductImportWorker;

class ImportProductsCommand extends Command
{
    protected $signature = 'products:import {--max-files=20}';
    protected $description = 'Imports CSV files from /imports/products';

    public function handle(ProductImportWorker $worker): int
    {
        $maxFiles = (int) $this->option('max-files');
        $worker->run($maxFiles);
        return self::SUCCESS;
    }
}
