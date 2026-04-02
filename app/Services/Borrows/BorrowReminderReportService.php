<?php

namespace App\Services\Borrows;

use App\Models\BorrowReminderLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BorrowReminderReportService
{
    /**
     * @return Collection<int, array{student_id:int,name:string,email:string,borrow_count:int,last_sent_at:string|null}>
     */
    public function emailedStudentsDueOn(Carbon $dueDate): Collection
    {
        $date = $dueDate->toDateString();

        $rows = BorrowReminderLog::query()
            ->join('borrows', 'borrow_reminder_logs.borrow_id', '=', 'borrows.id')
            ->join('students', 'borrow_reminder_logs.student_id', '=', 'students.id')
            ->where('borrow_reminder_logs.type', BorrowDueSoonReminderService::TYPE_DUE_SOON)
            ->where('borrow_reminder_logs.channel', BorrowDueSoonReminderService::CHANNEL_EMAIL)
            ->whereDate('borrows.due_date', $date)
            ->groupBy('students.id', 'students.first_name', 'students.middle_name', 'students.last_name', 'students.suffix', 'students.email')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select([
                'students.id as student_id',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
                'students.suffix',
                'students.email',
                DB::raw('COUNT(DISTINCT borrow_reminder_logs.borrow_id) as borrow_count'),
                DB::raw('MAX(borrow_reminder_logs.sent_at) as last_sent_at'),
            ])
            ->get();

        return $rows->map(function ($r) {
            $parts = array_filter([
                $r->first_name,
                $r->middle_name,
                $r->last_name,
            ]);
            $name = trim(implode(' ', $parts));
            if (!empty($r->suffix)) {
                $name .= ' ' . $r->suffix;
            }

            return [
                'student_id' => (int) $r->student_id,
                'name' => $name,
                'email' => (string) $r->email,
                'borrow_count' => (int) $r->borrow_count,
                'last_sent_at' => $r->last_sent_at,
            ];
        });
    }
}
