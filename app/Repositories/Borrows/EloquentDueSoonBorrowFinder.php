<?php

namespace App\Repositories\Borrows;

use App\Contracts\Borrows\DueSoonBorrowFinder;
use App\Models\Borrow;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EloquentDueSoonBorrowFinder implements DueSoonBorrowFinder
{
    public function findBorrowedDueOn(Carbon $dueDate): Collection
    {
        return Borrow::query()
            ->where('status', 'borrowed')
            ->whereDate('due_date', $dueDate->toDateString())
            ->with(['student', 'book', 'book.catalogue'])
            ->get();
    }
}
