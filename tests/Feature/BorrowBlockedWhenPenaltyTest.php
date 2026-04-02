<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Borrow;
use App\Models\Branch;
use App\Models\Catalogue;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowBlockedWhenPenaltyTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_with_unpaid_penalty_cannot_borrow(): void
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

        $user = User::query()->create([
            'employee_id' => 'E-1',
            'employee_type' => 'assistant',
            'email' => 'admin@example.com',
            'username' => 'admin1',
            'password' => 'secret',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum');

        $catalogue = Catalogue::query()->create([
            'acquisition_id' => null,
            'number_of_copies' => 2,
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

        $bookWithPenalty = Book::query()->create([
            'catalogue_id' => $catalogue->id,
            'branch_id' => $branch->id,
            'copy_number' => 1,
            'reference_number' => 'REF-PENALTY',
            'qr_code' => 'QR-PENALTY',
            'is_archived' => false,
            'is_borrowed' => false,
            'created_by' => 'test',
            'updated_by' => 'test',
            'book_status' => 'active',
            'expiration_date' => null,
        ]);

        $bookToBorrow = Book::query()->create([
            'catalogue_id' => $catalogue->id,
            'branch_id' => $branch->id,
            'copy_number' => 2,
            'reference_number' => 'REF-NEW',
            'qr_code' => 'QR-NEW',
            'is_archived' => false,
            'is_borrowed' => false,
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

        Borrow::query()->create([
            'student_id' => $student->id,
            'book_id' => $bookWithPenalty->id,
            'borrow_date' => Carbon::today()->subDays(10)->toDateString(),
            'due_date' => Carbon::today()->subDays(1)->toDateString(),
            'return_date' => null,
            'status' => 'overdue',
            'is_extended' => false,
            'extension_days' => null,
            'is_penalized' => true,
            'is_archived' => false,
            'penalty_amount' => 80,
            'remarks' => null,
            'is_fine_paid' => false,
            'created_by' => 'test',
            'updated_by' => 'test',
        ]);

        $response = $this->postJson('/api/borrow', [
            'reference_number' => $bookToBorrow->reference_number,
            'student_id' => $student->id,
            'duration' => '7',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('message', 'Cannot borrow: student has an unpaid penalty/fine.');

        $this->assertDatabaseMissing('borrows', [
            'student_id' => $student->id,
            'book_id' => $bookToBorrow->id,
            'status' => 'borrowed',
        ]);
    }
}
