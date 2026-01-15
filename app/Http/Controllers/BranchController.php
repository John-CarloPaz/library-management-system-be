<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Services\ListQueryService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private ListQueryService $lists,
    ) {
    }
    // Fetch all branches
    public function index(Request $request)
    {
        $branches = $this->lists->build(
            $request,
            Branch::query(),
            [
                'archived_field' => 'is_archived',
                'search_fields' => ['name', 'address'],
            ]
        );

        return response()->json($branches, 200);
    }

    // Fetch active (non-archived) branches
    public function listActive(Request $request)
    {
        $branches = $this->lists->build(
            $request,
            Branch::query()->where('is_archived', false),
            [
                'archived_field' => 'is_archived',
            ]
        );

        return response()->json($branches, 200);
    }

    // Fetch a specific branch by ID
    public function show($id)
    {
        $branch = Branch::findOrFail($id);
        return response()->json($branch, 200);
    }

    // Create a new branch
    public function createBranch(Request $request)
    {
        if (!$this->permissions->canManageBranches(Auth::user())) {
            return response()->json(['message' => 'Access denied: Only Super Admin can manage branches.'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'details' => 'required|string|max:255',
            'public_ip' => 'nullable|ip|required_without:public_ipv6',
            'public_ipv6' => 'nullable|ip|required_without:public_ip',
            'is_main_branch' => 'sometimes|boolean',
            'is_archived' => 'sometimes|boolean',
            'created_by' => 'sometimes|string|max:255',
            'updated_by' => 'sometimes|string|max:255',
        ]);
        $validatedData['is_archived'] = $request->input('is_archived', false);
        $validatedData['created_by'] = Auth::user()->username;
        $validatedData['updated_by'] = Auth::user()->username;

        if ($validatedData['is_main_branch'] ?? false) {
            // Check if a main branch already exists
            $existingMainBranch = Branch::where('is_main_branch', true)->exists();

            if ($existingMainBranch) {
                return response()->json(['message' => 'A main branch already exists. Only one main branch is allowed.'], 400);
            }
        }

        Branch::create($validatedData);

        return response()->json(['message' => 'Branch created successfully'], 201);
    }

    // Update an existing branch
    public function editBranch(Request $request, $id)
    {
        if (!$this->permissions->canManageBranches(Auth::user())) {
            return response()->json(['message' => 'Access denied: Only Super Admin can manage branches.'], 403);
        }

        $branch = Branch::findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string|max:255',
            'details' => 'sometimes|required|string|max:255',
            'public_ip' => 'sometimes|nullable|ip',
            'public_ipv6' => 'sometimes|nullable|ip',
            'is_main_branch' => 'sometimes|boolean',
            'is_archived' => 'sometimes|boolean',
            'updated_by' => 'sometimes|string|max:255',
        ]);

        if ($validatedData['is_main_branch'] ?? false) {
            // Check if a main branch already exists, excluding the current branch
            $existingMainBranch = Branch::where('is_main_branch', true)
                ->where('id', '!=', $branch->id)
                ->exists();

            if ($existingMainBranch) {
                return response()->json(['message' => 'A main branch already exists. Only one main branch is allowed.'], 400);
            }
        }

        $validatedData['updated_by'] = Auth::user()->username;

        $branch->update($validatedData);

        return response()->json(['message' => 'Branch updated successfully'], 200);
    }

    // Archive a branch
    public function archiveBranch($id)
    {
        if (!$this->permissions->canManageBranches(Auth::user())) {
            return response()->json(['message' => 'Access denied: Only Super Admin can manage branches.'], 403);
        }

        $branch = Branch::findOrFail($id);

        $hasActiveUsers = User::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->exists();

        if ($hasActiveUsers) {
            return response()->json(['message' => 'Cannot archive branch. Some users are still active.'], 400);
        }

        // Business rule: branch cannot be archived when there are available catalogues in this branch
        $hasAvailableCatalogues = \App\Models\Catalogue::where('branch_id', $branch->id)
            ->where('cataloging_status', 'available')
            ->where('is_archived', false)
            ->exists();

        if ($hasAvailableCatalogues) {
            return response()->json([
                'message' => 'Cannot archive branch. There are available catalogues in this branch.',
            ], 400);
        }

        $branch->update([
            'is_archived' => true,
            'updated_by' => Auth::user()->username,
        ]);

        return response()->json(['message' => 'Branch archived successfully'], 200);
    }

    public function restoreBranch($id)
    {
        if (!$this->permissions->canManageBranches(Auth::user())) {
            return response()->json(['message' => 'Access denied: Only Super Admin can manage branches.'], 403);
        }

        $branch = Branch::findOrFail($id);

        $branch->update([
            'is_archived' => false,
            'updated_by' => Auth::user()->username,
        ]);

        return response()->json(['message' => 'Branch restored successfully'], 200);
    }
}
