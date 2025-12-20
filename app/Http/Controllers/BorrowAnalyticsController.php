<?php

namespace App\Http\Controllers;

use App\Models\Borrow;
use App\Models\Book;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BorrowAnalyticsController extends Controller
{
    /**
     * Overall borrowing statistics
     */
    public function overview()
    {
        return response()->json([
            'total_borrows' => Borrow::count(),
            'active_borrows' => Borrow::where('status', 'borrowed')->count(),
            'returned_borrows' => Borrow::where('status', 'returned')->count(),
            'overdue_borrows' => Borrow::where('status', 'overdue')->count(),
            'penalized_borrows' => Borrow::where('is_penalized', true)->count(),
            'overdue_rate_percent' => $this->overdueRate(),
        ]);
    }

    /**
     * Most borrowed books
     */
    public function mostBorrowedBooks()
    {
        $books = Borrow::select('book_id', DB::raw('COUNT(*) as borrow_count'))
            ->groupBy('book_id')
            ->orderByDesc('borrow_count')
            ->with('book.catalogue')
            ->limit(10)
            ->get();

        return response()->json($books);
    }

    /**
     * Most active borrowers (students)
     */
    public function topBorrowers()
    {
        $students = Borrow::select('student_id', DB::raw('COUNT(*) as borrow_count'))
            ->groupBy('student_id')
            ->orderByDesc('borrow_count')
            ->with('student')
            ->limit(10)
            ->get();

        return response()->json($students);
    }

    /**
     * Borrow trends (daily or monthly)
     */
    public function borrowTrends($range = 'daily')
    {
        if ($range === 'monthly') {
            $data = Borrow::select(
                DB::raw('YEAR(borrow_date) as year'),
                DB::raw('MONTH(borrow_date) as month'),
                DB::raw('COUNT(*) as total')
            )
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get();
        } else {
            $data = Borrow::select(
                DB::raw('DATE(borrow_date) as date'),
                DB::raw('COUNT(*) as total')
            )
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        }

        return response()->json($data);
    }

    /**
     * Average borrow duration (days)
     */
    public function averageBorrowDuration()
    {
        $avg = Borrow::whereNotNull('return_date')
            ->select(DB::raw('AVG(DATEDIFF(return_date, borrow_date)) as avg_days'))
            ->value('avg_days');

        return response()->json([
            'average_borrow_days' => round($avg, 2)
        ]);
    }

    /**
     * Overdue rate calculation
     */
    private function overdueRate(): float
    {
        $total = Borrow::count();
        if ($total === 0) {
            return 0;
        }

        $overdue = Borrow::where('status', 'overdue')->count();
        return round(($overdue / $total) * 100, 2);
    }
}
