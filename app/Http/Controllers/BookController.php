<?php

namespace App\Http\Controllers;

use App\Events\GenericActionEvent;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookController extends Controller
{
    public function editBookStatus(Request $request, $id)
    {
        $validated = $this->validateBookStatus($request);

        $book = Book::findOrFail($id);
        $user = Auth::user();

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $book->update(array_merge($validated, ['updated_by' => $user->username]));


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
        $books = Book::with('catalogue', 'branch')->get();

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

    public function listArchivedBooks(Request $request)
    {
        $books = Book::with('catalogue', 'branch')->where('is_archived', true)->get();

        return response()->json([
            'archived_books' => $books,
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
            'status' => 'required|in:available,for_archiving,lost,damaged,under_repair,borrowed',
        ]);
    }
}
