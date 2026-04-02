<?php

namespace App\Services\Borrows;

use App\Contracts\Borrows\DueSoonBorrowFinder;
use App\Contracts\Borrows\BorrowReminderLogRepository;
use App\Contracts\Mail\TransactionalEmailClient;
use App\Models\Borrow;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class BorrowDueSoonReminderService
{
    public const TYPE_DUE_SOON = 'due_soon';
    public const CHANNEL_EMAIL = 'email';
    public const PROVIDER_BREVO_SMTP = 'brevo_smtp';

    public function __construct(
        private readonly TransactionalEmailClient $emailClient,
        private readonly BorrowReminderLogRepository $reminderLogs,
        private readonly DueSoonBorrowFinder $dueSoonBorrowFinder,
    ) {
    }

    /**
     * Sends due-soon reminder emails for borrows due on $dueDate.
     *
     * @return array{
     *   sent: array<int, array{student_id:int,name:string,email:string,borrow_count:int}>,
     *   already_emailed: array<int, array{student_id:int,name:string,email:string,borrow_count:int}>,
     *   failed: array<int, array{student_id:int,name:string,email:string|null,error:string}>
     * }
     */
    public function sendDueSoonReminders(Carbon $dueDate): array
    {
        $borrows = $this->dueSoonBorrowFinder->findBorrowedDueOn($dueDate);

        if ($borrows->isEmpty()) {
            return [
                'sent' => [],
                'already_emailed' => [],
                'failed' => [],
            ];
        }

        $allBorrowIds = $borrows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $alreadySentBorrowIds = $this->reminderLogs->alreadySentBorrowIds(
            $allBorrowIds,
            self::TYPE_DUE_SOON,
            self::CHANNEL_EMAIL,
        );
        $alreadySent = collect($alreadySentBorrowIds)->flip();

        $pendingBorrows = $borrows->filter(fn (Borrow $b) => !$alreadySent->has($b->id));
        $alreadyBorrows = $borrows->filter(fn (Borrow $b) => $alreadySent->has($b->id));

        $results = [
            'sent' => [],
            'already_emailed' => [],
            'failed' => [],
        ];

        foreach ($this->groupByStudent($pendingBorrows) as $studentId => $studentBorrows) {
            $student = $studentBorrows->first()?->student;
            if (!$student) {
                continue;
            }

            $studentEmail = $student->email;
            $studentName = $this->formatStudentName($student);

            if (!$studentEmail) {
                $results['failed'][] = [
                    'student_id' => (int) $student->id,
                    'name' => $studentName,
                    'email' => null,
                    'error' => 'Missing student email',
                ];
                continue;
            }

            $subject = 'Library Due Date Reminder';
            $html = $this->buildEmailHtml($studentName, $dueDate, $studentBorrows);

            try {
                $messageId = $this->emailClient->send($studentEmail, $studentName, $subject, $html);

                $sentAt = Carbon::now();
                foreach ($studentBorrows as $borrow) {
                    $this->reminderLogs->logSent(
                        (int) $borrow->id,
                        (int) $student->id,
                        self::TYPE_DUE_SOON,
                        self::CHANNEL_EMAIL,
                        self::PROVIDER_BREVO_SMTP,
                        $messageId,
                        $sentAt,
                    );
                }

                $results['sent'][] = [
                    'student_id' => (int) $student->id,
                    'name' => $studentName,
                    'email' => $studentEmail,
                    'borrow_count' => $studentBorrows->count(),
                ];
            } catch (Throwable $e) {
                $results['failed'][] = [
                    'student_id' => (int) $student->id,
                    'name' => $studentName,
                    'email' => $studentEmail,
                    'error' => $e->getMessage(),
                ];
            }
        }

        foreach ($this->groupByStudent($alreadyBorrows) as $studentId => $studentBorrows) {
            $student = $studentBorrows->first()?->student;
            if (!$student) {
                continue;
            }

            $results['already_emailed'][] = [
                'student_id' => (int) $student->id,
                'name' => $this->formatStudentName($student),
                'email' => $student->email,
                'borrow_count' => $studentBorrows->count(),
            ];
        }

        return $results;
    }

    /**
     * @param Collection<int, Borrow> $borrows
     * @return array<int, Collection<int, Borrow>>
     */
    private function groupByStudent(Collection $borrows): array
    {
        /** @var array<int, Collection<int, \App\Models\Borrow>> $grouped */
        $grouped = $borrows->groupBy(fn ($b) => (int) $b->student_id)->all();
        return $grouped;
    }

    private function formatStudentName($student): string
    {
        $parts = [
            $student->first_name,
            $student->middle_name,
            $student->last_name,
        ];
        $name = trim(implode(' ', array_filter($parts)));
        if (!empty($student->suffix)) {
            $name .= ' ' . $student->suffix;
        }
        return $name;
    }

    /**
     * @param Collection<int, Borrow> $borrows
     */
    private function buildEmailHtml(string $studentName, Carbon $dueDate, Collection $borrows): string
    {
        $rows = '';
        foreach ($borrows as $borrow) {
            $bookTitle = $borrow->book?->catalogue?->title
                ?? $borrow->book?->reference_number
                ?? 'Book';
            $rows .= '<li>' . e($bookTitle) . ' (Due: ' . e($dueDate->toFormattedDateString()) . ')</li>';
        }

        return <<<HTML
<p>Hi {$this->escape($studentName)},</p>
<p>This is a friendly reminder that the following borrowed book(s) are due <strong>tomorrow</strong> ({$this->escape($dueDate->toFormattedDateString())}):</p>
<ul>
{$rows}
</ul>
<p>Please return or renew on time to avoid penalties.</p>
<p>Thank you.</p>
HTML;
    }

    private function escape(string $value): string
    {
        return e($value);
    }
}
