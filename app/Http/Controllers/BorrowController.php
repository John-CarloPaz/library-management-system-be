<?php

namespace App\Http\Controllers;

use App\Events\GenericActionEvent;
use App\Models\Book;
use App\Models\Borrow;
use App\Services\ListQueryService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class BorrowController extends Controller
{
    public function __construct(private ListQueryService $lists)
    {
    }
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

        $hasUnpaidPenalty = Borrow::query()
            ->where('student_id', $validated['student_id'])
            ->where('is_archived', false)
            ->where('is_penalized', true)
            ->where('is_fine_paid', false)
            ->exists();

        if ($hasUnpaidPenalty) {
            return response()->json([
                'message' => 'Cannot borrow: student has an unpaid penalty/fine.',
            ], 400);
        }

        $book = Book::where('reference_number', $validated['reference_number'])->first();

        if (!$book) {
            return response()->json(['message' => 'Book not found'], 404);
        }

        if ($book->is_archived) {
            return response()->json(['message' => 'Book is archived'], 400);
        }

        // Business rule: user cannot borrow books from a different branch
        // Super admin may bypass branch restrictions.
        $user = auth()->user();
        if ($user && $user->role !== 'super_admin' && $user->branch_id !== null && $book->branch_id !== $user->branch_id) {
            return response()->json([
                'message' => 'Cannot borrow a book from a different branch.',
            ], 403);
        }

        if (
            $book->book_status !== 'active' ||
            $book->status === 'lost' ||
            $book->status === 'under_repair' ||
            Borrow::where('book_id', $book->id)
                ->whereIn('status', ['borrowed', 'overdue'])
                ->exists()
        ) {
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

        // mark the physical copy as currently borrowed without
        // overloading the book_status enum
        $book->update([
            'is_borrowed' => true,
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

        // Branch restriction: only same-branch users or super_admin may modify
        $user = auth()->user();
        if ($user && $user->role !== 'super_admin' && $user->branch_id !== null && $borrow->book->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Access denied: cannot modify borrow record from a different branch.'], 403);
        }


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
         * SYNC BOOK FLAGS (status + is_borrowed)
         */
        if (isset($validated['status'])) {
            match ($validated['status']) {
                'returned' => $borrow->book->update([
                    'book_status' => 'active',
                    'is_borrowed' => false,
                    'updated_by' => auth()->user()->username,
                ]),
                'borrowed', 'overdue' => $borrow->book->update([
                    'is_borrowed' => true,
                    'updated_by' => auth()->user()->username,
                ]),
                'lost' => $borrow->book->update([
                    'book_status' => 'lost',
                    'is_borrowed' => false,
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
public function extendBorrowing(Request $request)
{
    $validated = $request->validate([
        'reference_number' => 'required|string',
        'extension_days'   => 'required|integer|min:1|max:30',
        'is_fine_paid'     => 'nullable|boolean',
    ]);

    $book = Book::where('reference_number', $validated['reference_number'])->firstOrFail();

    // get active borrow (not returned)
    $borrow = $book->borrows()
        ->where('status', '!=', 'returned')
        ->first();

    // Branch restriction: only same-branch users or super_admin may extend
    $user = auth()->user();
    if ($user && $user->role !== 'super_admin' && $user->branch_id !== null && $book->branch_id !== $user->branch_id) {
        return response()->json(['message' => 'Access denied: cannot extend borrowing for a book from a different branch.'], 403);
    }

    if (!$borrow) {
        return response()->json([
            'message' => 'No active borrowing record found for this book'
        ], 404);
    }

    // already extended once
    if ($borrow->is_extended) {
        return response()->json([
            'message' => 'Borrowing period has already been extended once'
        ], 400);
    }

    /**
     * OVERDUE FLOW
     */
    if ($borrow->status === 'overdue') {
        if (!$validated['is_fine_paid']) {
            return response()->json([
                'message' => 'Settle fine first before extending borrowing period'
            ], 400);
        }

        // fine paid → reset status
        $borrow->update([
            'status'       => 'borrowed',
            'is_fine_paid' => true,
        ]);
    }

    /**
     * FINAL STATUS CHECK
     */
    if ($borrow->status !== 'borrowed') {
        return response()->json([
            'message' => 'Only borrowed books can be extended'
        ], 400);
    }

    /**
     * EXTENSION
     */
    $borrow->update([
        'due_date'       => Carbon::parse($borrow->due_date)->addDays($validated['extension_days']),
        'extension_days' => $validated['extension_days'],
        'is_extended'    => true,
        'updated_by'     => auth()->id(),
    ]);

    $this->logEvent('borrow', 'extend', $borrow->id);

    return response()->json([
        'message' => 'Borrowing period extended successfully',
        'borrow'  => $borrow,
    ]);
}


    /**
     * Unified function for returning a book or updating status, with overdue/lost/penalty logic
     */
    public function processReturnOrStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'is_fine_paid' => 'nullable|boolean',
            'remarks' => 'nullable|string',
            'status' => 'nullable|in:lost,returned,overdue,borrowed',
        ]);

        $borrow = Borrow::with(['student', 'book.catalogue'])->findOrFail($id);
        $user = auth()->user();

        // Branch restriction: only same-branch users or super_admin may perform returns/status updates
        if ($user && $user->role !== 'super_admin' && $user->branch_id !== null && $borrow->book->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Access denied: cannot update borrow status for a different branch.'], 403);
        }
        $now = now();

        // LOST LOGIC
        if (($validated['status'] ?? null) === 'lost') {
            $update = [
                'status' => 'lost',
                'is_penalized' => true,
                'penalty_amount' => 500,
                'updated_by' => $user->id,
            ];
            if (array_key_exists('is_fine_paid', $validated)) {
                $update['is_fine_paid'] = $validated['is_fine_paid'];
            } 
            $borrow->update($update);
            $borrow->book->update([
                'book_status' => 'lost',
                'is_borrowed' => false,
                'updated_by' => $user->username,
            ]);
            $this->logEvent('borrow', 'update_status', $borrow->id);
            return response()->json([
                'message' => 'Book marked as lost. ₱500 penalty applied.',
                'borrow' => $borrow->fresh(['student', 'book.catalogue'])
            ]);
        }

        // If already lost, cannot return
        if ($borrow->status === 'lost') {
            return response()->json([
                'message' => 'Cannot return a book that is marked as lost.',
                'borrow' => $borrow->fresh(['student', 'book.catalogue'])
            ], 400);
        }

        // If overdue, cannot return until fine is paid
        if ($borrow->status === 'overdue') {
            if (!($validated['is_fine_paid'] ?? $borrow->is_fine_paid)) {
                return response()->json([
                    'message' => 'Book is overdue. Please settle the payment before returning.',
                    'borrow' => $borrow->fresh(['student', 'book.catalogue'])
                ], 400);
            }
        }

        // Allow return
        $borrow->update([
            'status' => 'returned',
            'return_date' => $now,
            'is_fine_paid' => $validated['is_fine_paid'] ?? $borrow->is_fine_paid,
            'remarks' => $validated['remarks'] ?? $borrow->remarks,
            'updated_by' => $user->id,
        ]);
        $borrow->book->update([
            'book_status' => 'active',
            'is_borrowed' => false,
            'updated_by' => $user->username,
        ]);
        $this->logEvent('borrow', 'return', $borrow->id);
        return response()->json([
            'message' => 'Book returned successfully',
            'borrow' => $borrow->fresh(['student', 'book.catalogue'])
        ]);
    }

    /**
     * Pay fine for overdue or lost books. If lost, ensure book status is lost. If overdue, after payment set status to returned.
     */
    public function payFine(Request $request, $id)
    {
        $validated = $request->validate([
            'is_fine_paid' => 'required|boolean',
        ]);

        $borrow = Borrow::with('book')->findOrFail($id);

        // Branch restriction: only same-branch users or super_admin may pay fines
        $user = auth()->user();
        if ($user && $user->role !== 'super_admin' && $user->branch_id !== null && $borrow->book->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Access denied: cannot pay fine for a borrow record from a different branch.'], 403);
        }

        if (!$borrow->is_penalized) {
            return response()->json([
                'message' => 'No penalty to pay for this borrow record'
            ], 400);
        }
        if ($borrow->is_fine_paid && $validated['is_fine_paid']) {
            return response()->json([
                'message' => 'Fine is already marked as paid'
            ], 400);
        }

        $borrow->update([
            'is_fine_paid' => $validated['is_fine_paid'],
            'updated_by' => auth()->id(),
        ]);

        // If lost, ensure book status is lost
        if ($borrow->status === 'lost') {
            $borrow->book->update([
                'book_status' => 'lost',
                'is_borrowed' => false,
                'updated_by' => auth()->user()->username,
            ]);
        }

        // If overdue and fine is paid, set status to returned
        if ($borrow->status === 'overdue' && $validated['is_fine_paid']) {
            $borrow->update([
                'status' => 'returned',
                'return_date' => now(),
                'updated_by' => auth()->id(),
            ]);
            $borrow->book->update([
                'book_status' => 'active',
                'is_borrowed' => false,
                'updated_by' => auth()->user()->username,
            ]);
        }

        $this->logEvent('borrow', 'pay_fine', $borrow->id);

        return response()->json([
            'message' => 'Fine payment status updated',
            'borrow' => $borrow
        ]);
    }
    /**
     * View all borrow records (auto-overdue)
     */
    public function getBookbyReferenceNumber(Request $request)
    {
        $validated = $request->validate([
            'reference_number' => 'required|string',
        ]);

        $book = Book::where('reference_number', $validated['reference_number'])->first();

        if (!$book) {
            return response()->json(['message' => 'Book not found'], 404);
        }

        $borrows = $book->borrows()->with(['student', 'book.catalogue'])->get();

        return response()->json($borrows);
    }
    
    public function index(Request $request)
    {
        $borrows = $this->lists->build(
            $request,
            Borrow::with(['student', 'book.catalogue']),
            [
                'status_field' => 'status',
                'archived_field' => 'is_archived',
                'branch_field' => 'book.branch_id',
                'search_fields' => ['student.student_id', 'book.reference_number'],
            ]
        );


        return response()->json($borrows);
    }

    public function returnBookDetails(Request $request)
    {

        $validated = $request->validate([
            'reference_number' => 'required|string',
        ]);

        $book = Book::where('reference_number', $validated['reference_number'])->first();
        $borrow = Borrow::with(['student', 'book.catalogue'])->where('book_id', $book->id)->where('status', '!=', 'returned')->first();

        if (!$book) {
            return response()->json([
                'message' => 'Book not found.'
            ], 404);
        }

        if ($borrow->status === 'returned') {
            return response()->json([
                'message' => 'This book has already been returned.'
            ], 400);
        }

        if ($borrow->status === 'lost') {
            return response()->json([
                'message' => 'This book is marked as lost.'
            ], 400);
        }

        return response()->json($borrow);
    }

    // All list variations for borrows (paginated/all, active/archived, by status)
    // are now handled via query parameters on index using ListQueryService.

    /**
     * Archive borrow record (ONLY returned)
     */
    public function archive($id)
    {
        $borrow = Borrow::findOrFail($id);

        // Branch restriction: only same-branch users or super_admin may archive
        $user = auth()->user();
        if ($user && $user->role !== 'super_admin' && $user->branch_id !== null && $borrow->book->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Access denied: cannot archive borrow record from a different branch.'], 403);
        }

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

        if ($borrow->status === 'overdue' && !$borrow->is_fine_paid || $borrow->status === 'lost' && !$borrow->is_fine_paid) {
            return response()->json([
                'message' => 'Cannot archive record with unpaid fine'
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
