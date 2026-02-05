<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['external_id', 'name', 'price', 'stock', 'active'];
}
