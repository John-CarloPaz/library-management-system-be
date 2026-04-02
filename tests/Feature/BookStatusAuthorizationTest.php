<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckBranchIp;
use App\Models\Book;
use App\Models\Branch;
use App\Models\Catalogue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookStatusAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalogue(Branch $branch): Catalogue
    {
        return Catalogue::query()->create([
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
    }

    public function test_branch_admin_can_edit_book_status_in_own_branch(): void
    {
        $this->withoutMiddleware([CheckBranchIp::class]);

        $branch = Branch::query()->create([
            'name' => 'Branch A',
            'address' => null,
            'details' => null,
            'public_ip' => null,
            'is_archived' => false,
            'is_main_branch' => false,
            'created_by' => 'test',
            'updated_by' => 'test',
        ]);

        $branchAdmin = User::query()->create([
            'employee_id' => 'BA-1',
            'employee_type' => 'assistant',
            'email' => 'branchadmin@example.com',
            'username' => 'branchadmin',
            'password' => 'secret',
            'first_name' => 'Branch',
            'last_name' => 'Admin',
            'role' => 'branch_admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($branchAdmin, 'sanctum');

        $catalogue = $this->seedCatalogue($branch);
        $book = Book::query()->create([
            'catalogue_id' => $catalogue->id,
            'branch_id' => $branch->id,
            'copy_number' => 1,
            'reference_number' => 'REF-1',
            'qr_code' => 'QR-1',
            'is_archived' => false,
            'is_borrowed' => false,
            'created_by' => 'test',
            'updated_by' => 'test',
            'book_status' => 'active',
            'expiration_date' => null,
        ]);

        $response = $this->postJson('/api/books/edit-status/' . $book->id, [
            'status' => 'lost',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'book_status' => 'lost',
        ]);
    }

    public function test_branch_admin_cannot_edit_book_status_in_other_branch(): void
    {
        $this->withoutMiddleware([CheckBranchIp::class]);

        $branchA = Branch::query()->create([
            'name' => 'Branch A',
            'address' => null,
            'details' => null,
            'public_ip' => null,
            'is_archived' => false,
            'is_main_branch' => false,
            'created_by' => 'test',
            'updated_by' => 'test',
        ]);

        $branchB = Branch::query()->create([
            'name' => 'Branch B',
            'address' => null,
            'details' => null,
            'public_ip' => null,
            'is_archived' => false,
            'is_main_branch' => false,
            'created_by' => 'test',
            'updated_by' => 'test',
        ]);

        $branchAdminA = User::query()->create([
            'employee_id' => 'BA-1',
            'employee_type' => 'assistant',
            'email' => 'branchadminA@example.com',
            'username' => 'branchadminA',
            'password' => 'secret',
            'first_name' => 'Branch',
            'last_name' => 'AdminA',
            'role' => 'branch_admin',
            'branch_id' => $branchA->id,
            'is_active' => true,
        ]);

        $this->actingAs($branchAdminA, 'sanctum');

        $catalogueB = $this->seedCatalogue($branchB);
        $bookB = Book::query()->create([
            'catalogue_id' => $catalogueB->id,
            'branch_id' => $branchB->id,
            'copy_number' => 1,
            'reference_number' => 'REF-B',
            'qr_code' => 'QR-B',
            'is_archived' => false,
            'is_borrowed' => false,
            'created_by' => 'test',
            'updated_by' => 'test',
            'book_status' => 'active',
            'expiration_date' => null,
        ]);

        $response = $this->postJson('/api/books/edit-status/' . $bookB->id, [
            'status' => 'damaged',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_role_cannot_edit_book_status(): void
    {
        $this->withoutMiddleware([CheckBranchIp::class]);

        $branch = Branch::query()->create([
            'name' => 'Branch A',
            'address' => null,
            'details' => null,
            'public_ip' => null,
            'is_archived' => false,
            'is_main_branch' => false,
            'created_by' => 'test',
            'updated_by' => 'test',
        ]);

        $admin = User::query()->create([
            'employee_id' => 'AD-1',
            'employee_type' => 'assistant',
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => 'secret',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum');

        $catalogue = $this->seedCatalogue($branch);
        $book = Book::query()->create([
            'catalogue_id' => $catalogue->id,
            'branch_id' => $branch->id,
            'copy_number' => 1,
            'reference_number' => 'REF-ADMIN',
            'qr_code' => 'QR-ADMIN',
            'is_archived' => false,
            'is_borrowed' => false,
            'created_by' => 'test',
            'updated_by' => 'test',
            'book_status' => 'active',
            'expiration_date' => null,
        ]);

        $response = $this->postJson('/api/books/edit-status/' . $book->id, [
            'status' => 'under_repair',
        ]);

        $response->assertStatus(403);
    }
}
