<?php

namespace App\Contracts\Borrows;

use Carbon\Carbon;

interface BorrowReminderLogRepository
{
    /**
     * @param array<int> $borrowIds
     * @return array<int> Borrow IDs that already have a log entry
     */
    public function alreadySentBorrowIds(array $borrowIds, string $type, string $channel): array;

    public function logSent(
        int $borrowId,
        int $studentId,
        string $type,
        string $channel,
        string $provider,
        ?string $providerMessageId,
        Carbon $sentAt
    ): void;
}
