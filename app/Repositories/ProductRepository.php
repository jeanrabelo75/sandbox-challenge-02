<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class ProductRepository
{
    public function upsertMany(array $rows): int
    {
        if (empty($rows)) return 0;

        DB::table('products')->upsert(
            $rows,
            ['external_id'],
            ['name', 'price', 'stock', 'active', 'updated_at']
        );

        return count($rows);
    }
}
