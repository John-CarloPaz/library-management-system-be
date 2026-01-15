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
        'semester_id',
        'expiration_date',
        'is_archived',
        'created_by',
        'updated_by',
        'qr_code'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }
    public function borrows()
    {
        return $this->hasMany(Borrow::class, 'student_id');
    }
}
