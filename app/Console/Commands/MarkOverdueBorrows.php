<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Borrow;
use Carbon\Carbon;

class MarkOverdueBorrows extends Command
{
    protected $signature = 'borrows:mark-overdue';
    protected $description = 'Mark all borrowed borrows as overdue if past due date and compute penalty';

    public function handle()
    {
        $now = Carbon::now();
        $borrows = Borrow::where('status', 'borrowed')->where('due_date', '<', $now)->get();
        foreach ($borrows as $borrow) {
            $overdueDays = $now->diffInDays(Carbon::parse($borrow->due_date));
            $borrow->update([
                'status' => 'overdue',
                'is_penalized' => true,
                'penalty_amount' => $overdueDays * 8,
            ]);
        }
        $this->info('Overdue borrows updated.');
    }
}
