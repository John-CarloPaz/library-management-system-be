<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'students';
    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'email',
        'student_number',
        'program',
        'year_level',
        'status',
        'created_by',
        'updated_by'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
