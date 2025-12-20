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
        'student_id',
        'program',
        'year_level',
        'status',
        'created_by',
        'updated_by',
        'qr_code'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function borrows()
    {
        return $this->hasMany(Borrow::class, 'student_id');
    }
}
