<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NoticeLogs extends Model
{
    protected $fillable = [
        'serviceid',
        'findlost',
        'noticetime',
        'bot'
    ];
}
