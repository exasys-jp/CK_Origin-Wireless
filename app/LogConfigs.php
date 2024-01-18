<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LogConfigs extends Model
{
    protected $fillable = [
        'serviceid',
        'configid',
        'bot',
        'minutes',
        'findlost',
        'truefalse'
    ];
}
