<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportError extends Model
{
    protected $fillable = ['import_file_id','line_number','external_id','message','raw_line'];
}
