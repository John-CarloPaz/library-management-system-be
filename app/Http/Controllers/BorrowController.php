<?php

namespace App\Http\Controllers;

use App\Models\Borrow;
use App\Models\Book;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Events\GenericActionEvent;

class BorrowController extends Controller
{
    /**
     * Borrow a book via QR scan (book + student)
     */
    public function borrowBook(Request $request)
    {
        $validated = $request->validate([
            'reference_number' => 'required|string',
            'student_id' => 'required|exists:students,id',
            'duration' => 'required|in:3,7,14,30',
        ]);

        $book = Book::where('reference_number', $validated['reference_number'])->first();

        if (!$book) {
            return response()->json(['message' => 'Book not found'], 404);
        }

        if ($book->is_archived) {
            return response()->json(['message' => 'Book is archived'], 400);
        }

        if ($book->book_status !== 'active') {
            return response()->json([
                'message' => 'Book cannot be borrowed (status: ' . $book->book_status . ')'
            ], 400);
        }

        $borrowDate = now();
        $dueDate = $borrowDate->copy()->addDays($validated['duration']);

        $borrow = Borrow::create([
            'student_id' => $validated['student_id'],
            'book_id' => $book->id,
            'borrow_date' => $borrowDate,
            'due_date' => $dueDate,
            'status' => 'borrowed',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $book->update([
            'book_status' => 'borrowed',
            'updated_by' => auth()->user()->username,
        ]);

        $this->logEvent('borrow', 'create', $borrow->id);

        return response()->json([
            'message' => 'Book borrowed successfully',
            'borrow' => $borrow
        ], 201);
    }

    public function updateBorrowRecord(Request $request, $id)
    {
        $validated = $request->validate([
            'due_date' => 'nullable|date',
            'status' => 'nullable|in:borrowed,returned,overdue,lost',
            'is_fine_paid' => 'nullable|boolean',
            'is_penalized' => 'nullable|boolean',
            'is_extended' => 'nullable|boolean',
            'extension_days' => 'nullable|integer|min:1|max:30',
            'remarks' => 'nullable|string',
        ]);

        $borrow = Borrow::with('book')->findOrFail($id);

        // Auto-update overdue first
        $borrow->markOverdueIfNeeded();

        /**
         * BUSINESS RULES
         */
        if (
            isset($validated['status']) &&
            $validated['status'] === 'returned' &&
            ($borrow->is_penalized && !($validated['is_fine_paid'] ?? $borrow->is_fine_paid))
        ) {
            return response()->json([
                'message' => 'Cannot mark as returned while fine is unpaid'
            ], 400);
        }

        if (
            isset($validated['status']) &&
            $validated['status'] === 'borrowed' &&
            isset($validated['due_date']) &&
            now()->gt(Carbon::parse($validated['due_date']))
        ) {
            return response()->json([
                'message' => 'Borrowed status cannot have a past due date'
            ], 400);
        }

        /**
         * APPLY UPDATES
         */
        $borrow->update(array_merge($validated, [
            'updated_by' => auth()->id(),
        ]));

        /**
         * SYNC BOOK STATUS
         */
        if (isset($validated['status'])) {
            match ($validated['status']) {
                'returned' => $borrow->book->update([
                    'book_status' => 'active',
                    'updated_by' => auth()->user()->username,
                ]),
                'borrowed', 'overdue' => $borrow->book->update([
                    'book_status' => 'borrowed',
                    'updated_by' => auth()->user()->username,
                ]),
                'lost' => $borrow->book->update([
                    'book_status' => 'lost',
                    'updated_by' => auth()->user()->username,
                ]),
                default => null,
            };
        }

        $this->logEvent('borrow', 'update', $borrow->id);

        return response()->json([
            'message' => 'Borrow record updated successfully',
            'borrow' => $borrow
        ]);
    }
    /**
     * Extend borrowing period (ONLY borrowed books)
     */
    public function extendBorrowing(Request $request, $id)
    {
        $validated = $request->validate([
            'extension_days' => 'required|integer|min:1|max:30',
        ]);

        $borrow = Borrow::with('book')->findOrFail($id);

        $borrow->markOverdueIfNeeded();

        if ($borrow->status !== 'borrowed') {
            return response()->json([
                'message' => 'Only borrowed books can be extended'
            ], 400);
        }

        $borrow->update([
            'due_date' => Carbon::parse($borrow->due_date)->addDays($validated['extension_days']),
            'is_extended' => true,
            'extension_days' => $validated['extension_days'],
            'updated_by' => auth()->id(),
        ]);

        $this->logEvent('borrow', 'extend', $borrow->id);

        return response()->json([
            'message' => 'Borrowing extended',
            'borrow' => $borrow
        ]);
    }

    /**
     * Return book (auto-check overdue + fine enforcement)
     */
    public function returnBook(Request $request, $id)
    {
        $validated = $request->validate([
            'is_fine_paid' => 'required|boolean',
            'remarks' => 'nullable|string',
        ]);

        $borrow = Borrow::with('book')->findOrFail($id);

        $borrow->markOverdueIfNeeded();

        if (!in_array($borrow->status, ['borrowed', 'overdue'])) {
            return response()->json([
                'message' => 'Only borrowed or overdue books can be returned'
            ], 400);
        }

        if ($borrow->is_penalized && !$validated['is_fine_paid']) {
            return response()->json([
                'message' => 'Penalty must be paid before returning the book'
            ], 400);
        }

        $borrow->update([
            'status' => 'returned',
            'return_date' => now(),
            'is_fine_paid' => $validated['is_fine_paid'],
            'remarks' => $validated['remarks'],
            'updated_by' => auth()->id(),
        ]);

        $borrow->book->update([
            'book_status' => 'active',
            'updated_by' => auth()->user()->username,
        ]);

        $this->logEvent('borrow', 'return', $borrow->id);

        return response()->json([
            'message' => 'Book returned successfully',
            'borrow' => $borrow
        ]);
    }

    /**
     * View all borrow records (auto-overdue)
     */
    public function index()
    {
        $borrows = Borrow::with(['student', 'book.catalogue'])
            ->get();

        $borrows->each->markOverdueIfNeeded();

        return response()->json($borrows);
    }

    /**
     * Archive borrow record (ONLY returned)
     */
    public function archive($id)
    {
        $borrow = Borrow::findOrFail($id);

        if ($borrow->status === 'borrowed') {
            return response()->json([
                'message' => 'Cannot archive an active borrowed record'
            ], 400);
        }

        if ($borrow->status !== 'returned') {
            return response()->json([
                'message' => 'Only returned records can be archived'
            ], 400);
        }

        $borrow->update([
            'is_archived' => true,
            'updated_by' => auth()->id(),
        ]);

        $this->logEvent('borrow', 'archive', $borrow->id);

        return response()->json(['message' => 'Borrow record archived']);
    }

    /**
     * Restore archived borrow record
     */
    public function restore($id)
    {
        $borrow = Borrow::where('id', $id)
            ->where('is_archived', true)
            ->first();

        if (!$borrow) {
            return response()->json(['message' => 'Archived record not found'], 404);
        }

        $borrow->update([
            'is_archived' => false,
            'updated_by' => auth()->id(),
        ]);

        $this->logEvent('borrow', 'restore', $borrow->id);

        return response()->json(['message' => 'Borrow record restored']);
    }

    /**
     * Centralized audit logging
     */
    private function logEvent(string $type, string $action, int $id): void
    {
        GenericActionEvent::dispatch([
            'resource_type' => $type,
            'action' => $action,
            'resource_id' => $id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);
    }
}
