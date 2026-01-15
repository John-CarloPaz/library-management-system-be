<?php

namespace App\Http\Controllers;

use App\Events\GenericActionEvent;
use App\Models\Book;
use App\Services\ListQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookController extends Controller
{
    public function __construct(private ListQueryService $lists)
    {
    }
    public function editBookStatus(Request $request, $id)
    {
        $validated = $this->validateBookStatus($request);

        $book = Book::findOrFail($id);
        $user = Auth::user();

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        // Map validated request status to the actual DB column
        $book->update([
            'book_status' => $validated['status'],
            'updated_by' => $user->username,
        ]);


        GenericActionEvent::dispatch([
            'resource_type' => 'book',
            'action' => 'update',
            'resource_id' => $book->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'message' => 'Book status updated successfully.',
            'book' => $book,
        ]);
    }

    public function archiveBook(Request $request, $id)
    {
        $book = Book::findOrFail($id);
        $user = Auth::user();

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        // Business rule: book cannot be archived when status is active or under_repair
        if (in_array($book->book_status, ['active', 'under_repair'], true)) {
            return response()->json([
                'message' => 'Cannot archive a book with status active or under_repair.',
            ], 400);
        }

        $book->update([
            'is_archived' => true,
            'updated_by' => $user->username,
        ]);

        GenericActionEvent::dispatch([
            'resource_type' => 'book',
            'action' => 'archive',
            'resource_id' => $book->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'message' => 'Book archived successfully.',
            'book' => $book,
        ]);
    }

    public function viewBook(Request $request, $id)
    {
        $book = Book::with('catalogue', 'branch')->findOrFail($id);

        return response()->json([
            'book' => $book,
        ]);
    }

    public function listBooks(Request $request)
    {
        $books = $this->lists->build(
            $request,
            Book::with('catalogue', 'branch'),
            [
                'status_field' => 'book_status',
                'archived_field' => 'is_archived',
                'branch_field' => 'branch_id',
                'search_fields' => ['reference_number', 'catalogue.title'],
            ]
        );

        return response()->json([
            'books' => $books,
        ]);
    }


    public function findBookByReferenceId(Request $request, $referenceId)
    {
        $book = Book::with('catalogue', 'branch')->where('reference_number', $referenceId)->firstOrFail();

        return response()->json([
            'book' => $book,
        ]);
    }

    public function restoreBook(Request $request, $id)
    {
        $book = Book::findOrFail($id);
        $user = Auth::user();

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $book->update([
            'is_archived' => false,
            'updated_by' => $user->username,
        ]);

        GenericActionEvent::dispatch([
            'resource_type' => 'book',
            'action' => 'restore',
            'resource_id' => $book->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'message' => 'Book restored successfully.',
            'book' => $book,
        ]);
    }

    private function validateBookStatus(Request $request)
    {
        return $request->validate([
            // This is the *logical* status coming from the client; we
            // map it to the book_status column in editBookStatus.
            // We intentionally keep borrowing state separate on
            // the is_borrowed flag instead of using a 'borrowed'
            // enum value here.
            'status' => 'required|in:active,for_archiving,lost,damaged,under_repair',
        ]);
    }
}
