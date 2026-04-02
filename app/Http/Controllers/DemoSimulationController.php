<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Borrow;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class DemoSimulationController extends Controller
{
    public function overdueBorrows(Request $request)
    {
        $actor = auth('sanctum')->user();

        // Allow passing a Sanctum token in the querystring for demo convenience.
        // The token string is still a real credential; treat it carefully.
        if (! $actor) {
            $tokenString = (string) $request->query('token', '');
            if ($tokenString !== '') {
                $pat = PersonalAccessToken::findToken($tokenString);
                $actor = $pat?->tokenable;
            }
        }

        $authorizedViaActor = $actor && in_array($actor->role, ['super_admin', 'branch_admin'], true);

        // Fallback: static simulation token (optional)
        $expected = (string) env('SIMULATION_TOKEN', '');
        $queryToken = (string) $request->query('token', '');
        $authorizedViaSimulationToken = $expected !== '' && hash_equals($expected, $queryToken);

        // In production, require either a privileged actor or SIMULATION_TOKEN.
        if (app()->environment('production') && ! $authorizedViaActor && ! $authorizedViaSimulationToken) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $studentNumber = 12471263;
        $referenceNumbers = [
            '620-6200042-1',
            '620-6200042-2',
            '620-6200042-3',
        ];

        $student = Student::query()->where('student_id', $studentNumber)->first();
        if (! $student) {
            return response()->json(['message' => 'Student not found', 'student_id' => $studentNumber], 404);
        }

        $now = Carbon::now();
        $borrowDate = $now->copy()->subDays(10)->toDateString();
        $dueDate = $now->copy()->subDays(3)->toDateString();
        $penaltyAmount = Carbon::parse($dueDate)->diffInDays($now) * 8;

        $results = [];
        foreach ($referenceNumbers as $ref) {
            $book = Book::query()->where('reference_number', $ref)->first();
            if (! $book) {
                $results[] = ['reference_number' => $ref, 'status' => 'missing_book'];
                continue;
            }

            $borrow = Borrow::query()
                ->where('student_id', $student->id)
                ->where('book_id', $book->id)
                ->orderByDesc('id')
                ->first();

            if (! $borrow) {
                $results[] = ['reference_number' => $ref, 'status' => 'missing_borrow'];
                continue;
            }

            $borrow->update([
                'borrow_date' => $borrowDate,
                'due_date' => $dueDate,
                'return_date' => null,
                'status' => 'overdue',
                'is_penalized' => true,
                'penalty_amount' => $penaltyAmount,
                'is_fine_paid' => false,
                'updated_by' => 'simulation',
            ]);

            $book->forceFill([
                'is_borrowed' => true,
                'updated_by' => 'simulation',
            ])->save();

            $results[] = [
                'reference_number' => $ref,
                'status' => 'overdue_set',
                'borrow_id' => $borrow->id,
                'student_id' => $student->id,
            ];
        }

        return response()->json([
            'message' => 'Simulation applied',
            'student_number' => $studentNumber,
            'due_date' => $dueDate,
            'results' => $results,
        ]);
    }
}
