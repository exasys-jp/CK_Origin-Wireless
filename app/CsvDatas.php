<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CsvDatas extends Model
{
    protected $fillable = [
        'date',
        'time',
        'location'
    ];
}
