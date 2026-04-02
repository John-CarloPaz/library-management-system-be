<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckBranchIp;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_admin_can_create_admin_only_in_own_branch(): void
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

        $branchAdmin = User::query()->create([
            'employee_id' => 'BA-1',
            'employee_type' => 'assistant',
            'email' => 'branchadmin@example.com',
            'username' => 'branchadmin',
            'password' => 'secret',
            'first_name' => 'Branch',
            'last_name' => 'Admin',
            'role' => 'branch_admin',
            'branch_id' => $branchA->id,
            'is_active' => true,
        ]);

        $this->actingAs($branchAdmin, 'sanctum');

        // Allowed: create admin in same branch (even if payload tries other branch, controller forces actor's branch)
        $resp = $this->postJson('/api/users/create-admin', [
            'username' => 'adminA1',
            'email' => 'adminA1@example.com',
            'employee_id' => 'A-1',
            'employee_type' => 'assistant',
            'password' => 'password123',
            'first_name' => 'A',
            'last_name' => 'One',
            'role' => 'admin',
            'branch_id' => $branchA->id,
            'is_active' => true,
        ]);

        $resp->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'adminA1@example.com',
            'role' => 'admin',
            'branch_id' => $branchA->id,
        ]);

        // Not allowed: attempt to create branch_admin (should 422 due to validation in:admin)
        $resp2 = $this->postJson('/api/users/create-admin', [
            'username' => 'badrole',
            'email' => 'badrole@example.com',
            'employee_id' => 'A-2',
            'employee_type' => 'assistant',
            'password' => 'password123',
            'first_name' => 'Bad',
            'last_name' => 'Role',
            'role' => 'branch_admin',
            'branch_id' => $branchA->id,
            'is_active' => true,
        ]);
        $resp2->assertStatus(422);

        // Not allowed: create admin for a different branch (should 403)
        $resp3 = $this->postJson('/api/users/create-admin', [
            'username' => 'adminB1',
            'email' => 'adminB1@example.com',
            'employee_id' => 'B-1',
            'employee_type' => 'assistant',
            'password' => 'password123',
            'first_name' => 'B',
            'last_name' => 'One',
            'role' => 'admin',
            'branch_id' => $branchB->id,
            'is_active' => true,
        ]);
        $resp3->assertStatus(403);
    }

    public function test_branch_admin_can_only_list_admins_within_their_branch(): void
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

        User::query()->create([
            'employee_id' => 'A-ADMIN',
            'employee_type' => 'assistant',
            'email' => 'adminA@example.com',
            'username' => 'adminA',
            'password' => 'secret',
            'first_name' => 'Admin',
            'last_name' => 'A',
            'role' => 'admin',
            'branch_id' => $branchA->id,
            'is_active' => true,
        ]);

        User::query()->create([
            'employee_id' => 'B-ADMIN',
            'employee_type' => 'assistant',
            'email' => 'adminB@example.com',
            'username' => 'adminB',
            'password' => 'secret',
            'first_name' => 'Admin',
            'last_name' => 'B',
            'role' => 'admin',
            'branch_id' => $branchB->id,
            'is_active' => true,
        ]);

        User::query()->create([
            'employee_id' => 'SA-1',
            'employee_type' => 'assistant',
            'email' => 'super@example.com',
            'username' => 'super',
            'password' => 'secret',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'role' => 'super_admin',
            'branch_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($branchAdminA, 'sanctum');

        $response = $this->getJson('/api/users/list-admins?status=admin&count=all');

        $response->assertStatus(200);
        $emails = collect($response->json('users'))->pluck('email')->all();

        $this->assertContains('adminA@example.com', $emails);
        $this->assertNotContains('adminB@example.com', $emails);
        $this->assertNotContains('super@example.com', $emails);
    }
}
