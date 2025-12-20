<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{

    public function testBroadcast()
    {
        broadcast(new \App\Events\GenericActionEvent(['test' => 'message']));
        return response()->json(['message' => 'Broadcast event dispatched']);
    }
    public function index()
    {
        return response()->json(['message' => 'UserController index method']);
    }

    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!auth()->attempt(['email' => $validatedData['email'], 'password' => $validatedData['password']])) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = auth()->user();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function getAllUsers() {
        try {
            $users = User::with('branch')->get();
            return response()->json(['users' => $users], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch users', 'error' => $e->getMessage()], 500);
        }
    }

    public function getUserById($id) {
        try {
            $user = User::findOrFail($id);
            $requestedBooks = $user->requestedProcurements;

            return response()->json(['user' => $user], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching user by ID: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch user', 'error' => $e->getMessage()], 500);
        }
    }

    public function editAdmin(Request $request, $id) {
        try {
            $validatedData = $request->validate([
                'username' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
                'employee_id' => 'sometimes|nullable|string|max:50|unique:users,employee_id,' . $id,
                'employee_type' => 'sometimes|required|in:dean,administrator,assistant,chief_librarian',
                'password' => 'sometimes|required|string|min:8',
                'first_name' => 'sometimes|required|string|max:100',
                'last_name' => 'sometimes|required|string|max:100',
                'middle_name' => 'sometimes|nullable|string|max:100',
                'suffix' => 'sometimes|nullable|string|max:50',
                'role' => 'sometimes|required|in:super_admin,branch_admin,admin',
                'branch_id' => 'sometimes|required|exists:branches,id',
            ]);

            $user = User::findOrFail($id);

            if (isset($validatedData['password'])) {
                $validatedData['password'] = bcrypt($validatedData['password']);
            }

            $user->update($validatedData);

            return response()->json(['message' => 'Admin user updated successfully', 'user' => $user], 200);
        } catch (\Exception $e) {
            Log::error('Error updating admin user: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update admin user', 'error' => $e->getMessage()], 500);
        }

    }

    public function createAdmin(Request $request) {
        try {
            // Log the incoming request data for debugging
            Log::info('Incoming request data:', $request->all());

            $validatedData = $request->validate([
                'username' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'employee_id' => 'nullable|string|max:50|unique:users',
                'employee_type' => 'required|in:dean,administrator,assistant,chief_librarian',
                'password' => 'required|string|min:8',
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'middle_name' => 'nullable|string|max:100',
                'suffix' => 'nullable|string|max:50',
                'role' => 'required|in:super_admin,branch_admin,admin',
                'branch_id' => 'required|exists:branches,id',
            ]);

            $user = User::create([
                'username' => $validatedData['username'],
                'email' => $validatedData['email'],
                'password' => bcrypt($validatedData['password']),
                'employee_id' => $validatedData['employee_id'] ?? null,
                'employee_type' => $validatedData['employee_type'],
                'first_name' => $validatedData['first_name'],
                'last_name' => $validatedData['last_name'],
                'middle_name' => $validatedData['middle_name'] ?? null,
                'suffix' => $validatedData['suffix'] ?? null,
                'role' => $validatedData['role'],
                'branch_id' => $validatedData['branch_id'] ?? null,
            ]);
            return response()->json(['message' => 'Admin user created successfully', 'user' => $user], 201);
        } catch (\Exception $e) {
            Log::error('Error creating admin user: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create admin user', 'error' => $e->getMessage()], 500);
        }
    }
}
