<?php

namespace Tests\Unit;

use App\Contracts\Borrows\BorrowReminderLogRepository;
use App\Contracts\Borrows\DueSoonBorrowFinder;
use App\Contracts\Mail\TransactionalEmailClient;
use App\Models\Book;
use App\Models\Borrow;
use App\Models\Catalogue;
use App\Models\Student;
use App\Services\Borrows\BorrowDueSoonReminderService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BorrowDueSoonReminderServiceTest extends TestCase
{
    public function test_it_sends_once_and_marks_as_already_emailed_next_time(): void
    {
        $dueDate = Carbon::tomorrow();

        $student = new Student();
        $student->id = 10;
        $student->first_name = 'Jane';
        $student->last_name = 'Doe';
        $student->email = 'jane@example.com';

        $catalogue = new Catalogue();
        $catalogue->title = 'Test Book';

        $book = new Book();
        $book->reference_number = 'REF-1';
        $book->setRelation('catalogue', $catalogue);

        $borrow = new Borrow();
        $borrow->id = 1;
        $borrow->student_id = 10;
        $borrow->setRelation('student', $student);
        $borrow->setRelation('book', $book);

        $finder = new class($borrow) implements DueSoonBorrowFinder {
            public function __construct(private readonly Borrow $borrow) {}
            public function findBorrowedDueOn(Carbon $dueDate): Collection
            {
                return collect([$this->borrow]);
            }
        };

        $emailClient = new class implements TransactionalEmailClient {
            public int $sendCount = 0;
            public function send(string $toEmail, string $toName, string $subject, string $htmlContent): ?string
            {
                $this->sendCount++;
                return 'msg-1';
            }
        };

        $repo = new class implements BorrowReminderLogRepository {
            /** @var array<string, bool> */
            private array $keys = [];

            public function alreadySentBorrowIds(array $borrowIds, string $type, string $channel): array
            {
                $sent = [];
                foreach ($borrowIds as $id) {
                    $key = $id . '|' . $type . '|' . $channel;
                    if (isset($this->keys[$key])) {
                        $sent[] = (int) $id;
                    }
                }
                return $sent;
            }

            public function logSent(int $borrowId, int $studentId, string $type, string $channel, string $provider, ?string $providerMessageId, Carbon $sentAt): void
            {
                $this->keys[$borrowId . '|' . $type . '|' . $channel] = true;
            }
        };

        $service = new BorrowDueSoonReminderService($emailClient, $repo, $finder);

        $first = $service->sendDueSoonReminders($dueDate);
        $this->assertCount(1, $first['sent']);
        $this->assertCount(0, $first['already_emailed']);
        $this->assertSame(1, $emailClient->sendCount);

        $second = $service->sendDueSoonReminders($dueDate);
        $this->assertCount(0, $second['sent']);
        $this->assertCount(1, $second['already_emailed']);
        $this->assertSame(1, $emailClient->sendCount);
    }
}
