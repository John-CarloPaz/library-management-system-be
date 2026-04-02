<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BorrowReminderLog extends Model
{
    protected $table = 'borrow_reminder_logs';

    protected $fillable = [
        'borrow_id',
        'student_id',
        'type',
        'channel',
        'provider',
        'provider_message_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function borrow()
    {
        return $this->belongsTo(Borrow::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
