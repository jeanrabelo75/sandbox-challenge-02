<?php

namespace Tests\Unit\ProductImport;

use App\DTOs\ProductImport\ParseResult;
use App\Models\ImportFile;
use App\Repositories\ImportFileRepository;
use App\Repositories\ProductRepository;
use App\Services\ProductImport\CsvProductFileParser;
use App\Services\ProductImport\ProductImportWorker;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ProductImportWorkerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeGenerator(array $batches): \Generator
    {
        foreach ($batches as $batch) {
            yield $batch;
        }
    }

    public function test_it_stops_when_there_is_no_file_to_process(): void
    {
        Log::spy();

        $importFileRepo = Mockery::mock(ImportFileRepository::class);
        $productRepo = Mockery::mock(ProductRepository::class);
        $parser = Mockery::mock(CsvProductFileParser::class);

        $importFileRepo->shouldReceive('discoverCsvFiles')->once();
        $importFileRepo->shouldReceive('claimNextFile')->once()->andReturn(null);

        $worker = new ProductImportWorker($importFileRepo, $productRepo, $parser, '/imports/products');
        $worker->run(20);

        $this->assertTrue(true);
    }

    public function test_it_processes_batches_and_marks_file_as_processed(): void
    {
        Log::spy();

        $importFileRepo = Mockery::mock(ImportFileRepository::class);
        $productRepo = Mockery::mock(ProductRepository::class);
        $parser = Mockery::mock(CsvProductFileParser::class);

        $file = new ImportFile();
        $file->id = 1;
        $file->file_path = '/imports/products/example.csv';

        $importFileRepo->shouldReceive('discoverCsvFiles')->once();
        $importFileRepo->shouldReceive('claimNextFile')->twice()->andReturn($file, null);

        $batches = [
            [['external_id' => 'A', 'name' => 'A', 'price' => 1.0, 'stock' => 0, 'active' => true]],
            [['external_id' => 'B', 'name' => 'B', 'price' => 2.0, 'stock' => 1, 'active' => false]],
        ];

        $parseResult = new ParseResult($this->makeGenerator($batches));
        $parseResult->success = 2;
        $parseResult->errors = 0;

        $parser->shouldReceive('parse')->once()->with($file->file_path, $file->id, 1000)->andReturn($parseResult);

        $productRepo->shouldReceive('upsertMany')->once()->with($batches[0])->andReturn(1);
        $productRepo->shouldReceive('upsertMany')->once()->with($batches[1])->andReturn(1);

        $importFileRepo->shouldReceive('markProcessed')
            ->once()
            ->withArgs(fn(...$args) => count($args) === 3 && $args[0] === $file && $args[1] === 2 && $args[2] === 0);

        $importFileRepo->shouldReceive('markError')->never();

        $worker = new ProductImportWorker($importFileRepo, $productRepo, $parser, '/imports/products');
        $worker->run(20);

        $this->assertTrue(true);
    }

    public function test_it_marks_file_as_error_when_parser_throws(): void
    {
        Log::spy();

        $importFileRepo = Mockery::mock(ImportFileRepository::class);
        $productRepo = Mockery::mock(ProductRepository::class);
        $parser = Mockery::mock(CsvProductFileParser::class);

        $file = new ImportFile();
        $file->id = 2;
        $file->file_path = '/imports/products/bad.csv';

        $importFileRepo->shouldReceive('discoverCsvFiles')->once();
        $importFileRepo->shouldReceive('claimNextFile')->twice()->andReturn($file, null);

        $parser->shouldReceive('parse')->once()->andThrow(new \RuntimeException('Invalid CSV header'));

        $importFileRepo->shouldReceive('markError')
            ->once()
            ->with($file, Mockery::type(\Throwable::class));

        $importFileRepo->shouldReceive('markProcessed')->never();

        $worker = new ProductImportWorker($importFileRepo, $productRepo, $parser, '/imports/products');
        $worker->run(20);

        $this->assertTrue(true);
    }
}
