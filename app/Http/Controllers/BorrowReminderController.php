<?php

namespace App\Http\Controllers;

use App\Services\Borrows\BorrowReminderReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BorrowReminderController extends Controller
{
    public function emailedStudents(Request $request, BorrowReminderReportService $report)
    {
        $validated = $request->validate([
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $dueDate = isset($validated['due_date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['due_date'])
            : Carbon::tomorrow();

        return response()->json([
            'due_date' => $dueDate->toDateString(),
            'type' => 'due_soon',
            'channel' => 'email',
            'data' => $report->emailedStudentsDueOn($dueDate)->values(),
        ]);
    }
}
