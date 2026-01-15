<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    protected $table = 'semesters';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_archived',
    ];

    public function students()
    {
        return $this->hasMany(Student::class, 'semester_id');
    }
}
