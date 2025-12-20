<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchController extends Controller
{
    // Fetch all branches
    public function index()
    {
        $branches = Branch::all();
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
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'details' => 'required|string|max:255',
            'public_ip' => 'required|ip',
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
        if (Auth::user()->role == 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $branch = Branch::findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string|max:255',
            'details' => 'sometimes|required|string|max:255',
            'public_ip' => 'sometimes|required|ip',
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
        $branch = Branch::findOrFail($id);

        $hasActiveUsers = User::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->exists();

        if ($hasActiveUsers) {
            return response()->json(['message' => 'Cannot archive branch. Some users are still active.'], 400);
        }

        $branch->update([
            'is_archived' => true,
            'updated_by' => Auth::user()->username,
        ]);

        return response()->json(['message' => 'Branch archived successfully'], 200);
    }

    public function restoreBranch($id)
    {
        $branch = Branch::findOrFail($id);

        $branch->update([
            'is_archived' => false,
            'updated_by' => Auth::user()->username,
        ]);

        return response()->json(['message' => 'Branch restored successfully'], 200);
    }
}
