<?php

namespace Tests\Unit\ProductImport;

use App\Repositories\ImportErrorRepository;
use App\Services\ProductImport\CsvProductFileParser;
use Mockery;
use Tests\TestCase;

class CsvProductFileParserTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function writeTempCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_products_');
        if ($path === false) {
            $this->fail('Failed to create temp file');
        }

        file_put_contents($path, $contents);
        return $path;
    }

    public function test_it_throws_and_logs_error_when_header_is_invalid(): void
    {
        $csv = "externalId;product_name;value;inventory;enabled\n" .
               "H300;Produto Header Ruim 1;10.00;5;true\n";

        $path = $this->writeTempCsv($csv);

        $errorRepo = Mockery::mock(ImportErrorRepository::class);
        $errorRepo->shouldReceive('logHeaderError')
            ->once()
            ->with(
                123,
                'externalId;product_name;value;inventory;enabled',
                'external_id;name;price;stock;active'
            );

        $parser = new CsvProductFileParser($errorRepo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid CSV header');

        $result = $parser->parse($path, 123, 1000);
        foreach ($result->batches as $_batch) {}
    }

    public function test_it_parses_valid_rows_and_logs_line_errors_without_aborting(): void
    {
        $csv =
            "external_id;name;price;stock;active\n" .
            "E200;Produto OK 1;10.00;5;true\n" .
            ";Sem external_id;25.00;10;true\n" .
            "E203;;25.00;10;true\n" .
            "E205;Preco Negativo;-5.00;10;true\n" .
            "E207;Stock Negativo;10.00;-1;true\n" .
            "E210;Active Invalido;10.00;10;yes\n" .
            "E211;Colunas a menos;10.00;10\n" .
            "E213;Produto OK 3;15.50;12;true\n";

        $path = $this->writeTempCsv($csv);

        $errorRepo = Mockery::mock(ImportErrorRepository::class);
        $errorRepo->shouldReceive('logHeaderError')->never();

        $errorRepo->shouldReceive('logLineError')->once()
            ->withArgs(fn($importFileId,$lineNumber,$externalId,$message,$rawLine) =>
                $importFileId===999 && $lineNumber===3 && $externalId==='' &&
                $message==='external_id is required' && str_starts_with($rawLine,';Sem external_id;')
            );

        $errorRepo->shouldReceive('logLineError')->once()
            ->withArgs(fn($importFileId,$lineNumber,$externalId,$message,$rawLine) =>
                $importFileId===999 && $lineNumber===4 && $externalId==='E203' &&
                $message==='name is required' && str_starts_with($rawLine,'E203;')
            );

        $errorRepo->shouldReceive('logLineError')->once()
            ->withArgs(fn($importFileId,$lineNumber,$externalId,$message,$rawLine) =>
                $importFileId===999 && $lineNumber===5 && $externalId==='E205' &&
                $message==='price must be a number > 0' && str_starts_with($rawLine,'E205;')
            );

        $errorRepo->shouldReceive('logLineError')->once()
            ->withArgs(fn($importFileId,$lineNumber,$externalId,$message,$rawLine) =>
                $importFileId===999 && $lineNumber===6 && $externalId==='E207' &&
                $message==='stock must be an integer >= 0' && str_starts_with($rawLine,'E207;')
            );

        $errorRepo->shouldReceive('logLineError')->once()
            ->withArgs(fn($importFileId,$lineNumber,$externalId,$message,$rawLine) =>
                $importFileId===999 && $lineNumber===7 && $externalId==='E210' &&
                $message==='active must be true|false' && str_starts_with($rawLine,'E210;')
            );

        $errorRepo->shouldReceive('logLineError')->once()
            ->withArgs(fn($importFileId,$lineNumber,$externalId,$message,$rawLine) =>
                $importFileId===999 && $lineNumber===8 && $externalId==='E211' &&
                $message==='Invalid column count' && str_starts_with($rawLine,'E211;')
            );

        $parser = new CsvProductFileParser($errorRepo);

        $result = $parser->parse($path, 999, 2);

        $allBatches = [];
        foreach ($result->batches as $batch) {
            $allBatches[] = $batch;
        }

        $flatten = array_merge(...$allBatches);
        $this->assertCount(2, $flatten);

        $this->assertSame('E200', $flatten[0]['external_id']);
        $this->assertSame('Produto OK 1', $flatten[0]['name']);
        $this->assertSame(10.00, $flatten[0]['price']);
        $this->assertSame(5, $flatten[0]['stock']);
        $this->assertTrue($flatten[0]['active']);

        $this->assertSame('E213', $flatten[1]['external_id']);
        $this->assertSame('Produto OK 3', $flatten[1]['name']);
        $this->assertSame(15.50, $flatten[1]['price']);
        $this->assertSame(12, $flatten[1]['stock']);
        $this->assertTrue($flatten[1]['active']);

        $this->assertSame(2, $result->success);
        $this->assertSame(6, $result->errors);
    }
}
