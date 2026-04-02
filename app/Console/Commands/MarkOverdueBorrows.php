<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Services\Borrows\BorrowDueSoonReminderService;
use App\Services\Borrows\OverdueBorrowService;

class MarkOverdueBorrows extends Command
{
    protected $signature = 'borrows:mark-overdue';
    protected $description = 'Send due-soon reminders and mark borrows overdue if past due date';

    public function handle(
        BorrowDueSoonReminderService $dueSoonReminderService,
        OverdueBorrowService $overdueBorrowService,
    )
    {
        $dueDate = Carbon::tomorrow();
        $results = $dueSoonReminderService->sendDueSoonReminders($dueDate);

        $sent = $results['sent'];
        $already = $results['already_emailed'];
        $failed = $results['failed'];

        $this->info('Due-soon reminder processing complete.');
        $this->line('Due date: ' . $dueDate->toDateString());
        $this->line('Sent emails: ' . count($sent));
        $this->line('Already emailed: ' . count($already));
        $this->line('Failed: ' . count($failed));

        if ($sent !== []) {
            $this->newLine();
            $this->info('Students emailed (due tomorrow):');
            $this->table(['Student ID', 'Name', 'Email', 'Borrow Count'], array_map(fn ($r) => [
                $r['student_id'],
                $r['name'],
                $r['email'],
                $r['borrow_count'],
            ], $sent));
        }

        if ($already !== []) {
            $this->newLine();
            $this->info('Students already emailed (skipped):');
            $this->table(['Student ID', 'Name', 'Email', 'Borrow Count'], array_map(fn ($r) => [
                $r['student_id'],
                $r['name'],
                $r['email'],
                $r['borrow_count'],
            ], $already));
        }

        if ($failed !== []) {
            $this->newLine();
            $this->error('Failed to send reminders:');
            $this->table(['Student ID', 'Name', 'Email', 'Error'], array_map(fn ($r) => [
                $r['student_id'],
                $r['name'],
                $r['email'],
                $r['error'],
            ], $failed));
        }

        $now = Carbon::now();
        $updated = $overdueBorrowService->markOverdue($now);
        $this->newLine();
        $this->info("Overdue borrows updated: {$updated}");
    }
}
