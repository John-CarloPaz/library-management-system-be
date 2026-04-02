<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Borrow;
use App\Models\BorrowReminderLog;
use App\Models\Branch;
use App\Models\Catalogue;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowReminderEmailedStudentsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_emailed_students_for_a_due_date(): void
    {
        $this->withoutMiddleware();

        $branch = Branch::query()->create([
            'name' => 'Main',
            'address' => null,
            'details' => null,
            'public_ip' => null,
            'is_archived' => false,
            'is_main_branch' => true,
            'created_by' => 'test',
            'updated_by' => 'test',
        ]);

        $catalogue = Catalogue::query()->create([
            'acquisition_id' => null,
            'number_of_copies' => 1,
            'title' => 'Test Book',
            'author' => 'Author',
            'edition' => null,
            'isbn' => null,
            'publisher' => null,
            'place_of_publication' => null,
            'year_of_publication' => null,
            'cataloging_status' => 'available',
            'is_provisional' => false,
            'is_archived' => false,
            'branch_id' => $branch->id,
            'created_by' => 'test',
            'updated_by' => 'test',
        ]);

        $book = Book::query()->create([
            'catalogue_id' => $catalogue->id,
            'branch_id' => $branch->id,
            'copy_number' => 1,
            'reference_number' => 'REF-1',
            'qr_code' => 'QR-1',
            'is_archived' => false,
            'created_by' => 'test',
            'updated_by' => 'test',
            'book_status' => 'active',
            'expiration_date' => null,
        ]);

        $student = Student::query()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'middle_name' => null,
            'suffix' => null,
            'email' => 'jane@example.com',
            'student_id' => 10001,
            'program' => 'BSCS',
            'year_level' => 1,
            'status' => 'active',
            'semester_id' => null,
            'expiration_date' => null,
            'is_archived' => false,
            'created_by' => 'test',
            'updated_by' => 'test',
            'qr_code' => null,
        ]);

        $dueDate = Carbon::tomorrow()->toDateString();

        $borrow = Borrow::query()->create([
            'student_id' => $student->id,
            'book_id' => $book->id,
            'borrow_date' => Carbon::today()->toDateString(),
            'due_date' => $dueDate,
            'return_date' => null,
            'status' => 'borrowed',
            'is_extended' => false,
            'extension_days' => null,
            'is_penalized' => false,
            'penalty_amount' => null,
            'remarks' => null,
            'is_fine_paid' => false,
            'is_archived' => false,
            'created_by' => 'test',
            'updated_by' => 'test',
        ]);

        BorrowReminderLog::query()->create([
            'borrow_id' => $borrow->id,
            'student_id' => $student->id,
            'type' => 'due_soon',
            'channel' => 'email',
            'provider' => 'brevo_smtp',
            'provider_message_id' => null,
            'sent_at' => Carbon::now(),
        ]);

        $response = $this->getJson('/api/borrows/reminders/emailed?due_date=' . $dueDate);

        $response
            ->assertOk()
            ->assertJsonPath('due_date', $dueDate)
            ->assertJsonPath('type', 'due_soon')
            ->assertJsonPath('channel', 'email')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', $student->id)
            ->assertJsonPath('data.0.email', 'jane@example.com')
            ->assertJsonPath('data.0.borrow_count', 1);
    }
}
