<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportFile extends Model
{
    protected $fillable = [
        'file_path','file_size','file_mtime','status','attempts',
        'locked_at','processed_at','last_error',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}

