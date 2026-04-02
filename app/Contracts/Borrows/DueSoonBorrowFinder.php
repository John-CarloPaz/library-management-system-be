<?php

namespace App\Contracts\Borrows;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface DueSoonBorrowFinder
{
    /**
     * @return Collection<int, \App\Models\Borrow>
     */
    public function findBorrowedDueOn(Carbon $dueDate): Collection;
}
