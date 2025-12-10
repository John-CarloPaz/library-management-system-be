<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deparments extends Model
{
    protected $table = "departments";

    protected $fillable = [
        'name',
        'short_name'
    ];

}
