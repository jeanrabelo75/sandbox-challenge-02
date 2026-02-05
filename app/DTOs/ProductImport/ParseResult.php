<?php

namespace App\DTOs\ProductImport;

class ParseResult
{
    public int $errors = 0;
    public int $success = 0;

    public function __construct(
        public \Generator $batches
    ) {}
}
