<?php

namespace App\Services\Borrows;

use App\Models\Borrow;
use Carbon\Carbon;

class OverdueBorrowService
{
    public function markOverdue(Carbon $now): int
    {
        $borrows = Borrow::query()
            ->where('status', 'borrowed')
            ->where('due_date', '<', $now->toDateString())
            ->get();

        $updated = 0;
        foreach ($borrows as $borrow) {
            $before = $borrow->status;
            $borrow->markOverdueIfNeeded();
            if ($before !== $borrow->status) {
                $updated++;
            }
        }

        return $updated;
    }
}
