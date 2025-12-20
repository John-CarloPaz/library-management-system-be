<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Borrow extends Model
{
    protected $fillable = [
        'student_id',
        'book_id',
        'borrow_date',
        'due_date',
        'return_date',
        'status',
        'is_extended',
        'extension_days',
        'is_penalized',
        'is_archived',
        'penalty_amount',
        'remarks',
        'is_fine_paid',
        'created_by',
        'updated_by',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function markOverdueIfNeeded(): void
    {
        if (
            $this->status === 'borrowed' &&
            Carbon::now()->gt($this->due_date)
        ) {
            $this->update([
                'status' => 'overdue',
                'is_penalized' => true,
            ]);
        }
    }

}
