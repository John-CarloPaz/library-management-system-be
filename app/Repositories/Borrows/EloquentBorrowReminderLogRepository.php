<?php

namespace App\Repositories\Borrows;

use App\Contracts\Borrows\BorrowReminderLogRepository;
use App\Models\BorrowReminderLog;
use Carbon\Carbon;

class EloquentBorrowReminderLogRepository implements BorrowReminderLogRepository
{
    public function alreadySentBorrowIds(array $borrowIds, string $type, string $channel): array
    {
        if ($borrowIds === []) {
            return [];
        }

        return BorrowReminderLog::query()
            ->whereIn('borrow_id', $borrowIds)
            ->where('type', $type)
            ->where('channel', $channel)
            ->pluck('borrow_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function logSent(
        int $borrowId,
        int $studentId,
        string $type,
        string $channel,
        string $provider,
        ?string $providerMessageId,
        Carbon $sentAt
    ): void {
        BorrowReminderLog::query()->updateOrCreate(
            [
                'borrow_id' => $borrowId,
                'type' => $type,
                'channel' => $channel,
            ],
            [
                'student_id' => $studentId,
                'provider' => $provider,
                'provider_message_id' => $providerMessageId,
                'sent_at' => $sentAt,
            ]
        );
    }
}
