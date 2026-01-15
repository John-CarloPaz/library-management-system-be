<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ListQueryService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private ListQueryService $lists,
    ) {
    }

    public function index(Request $request)
    {
        return $this->getAllUsers($request);
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

        // Prevent inactive admin users from logging in
        if (in_array($user->role, ['super_admin', 'branch_admin', 'admin'], true) && !$user->is_active) {
            auth()->logout();

            return response()->json([
                'message' => 'Your account is inactive. Please contact an administrator.',
            ], 403);
        }

        // Update last login timestamp for active users
        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

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

    public function getAllUsers(Request $request) {
        try {
            if (!$this->permissions->canManageAdmins(auth()->user()) && !$this->permissions->canViewAdminDetails(auth()->user())) {
                return response()->json(['message' => 'Access denied: Only Super Admin can manage admins.'], 403);
            }

            $users = $this->lists->build(
                $request,
                User::with('branch'),
                [
                    // status=super_admin|branch_admin|admin
                    'status_field' => 'role',
                    // is_active=true|false
                    'boolean_fields' => [
                        'is_active' => 'is_active',
                    ],
                    // allow filtering by branch via ?branch_id= or ?branch=
                    'branch_field' => 'branch_id',
                    // allow simple search via ?search= or ?q= across common user fields
                    'search_fields' => ['username', 'email', 'first_name', 'last_name'],
                ]
            );
            return response()->json(['users' => $users], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch users', 'error' => $e->getMessage()], 500);
        }
    }

    public function getUserById($id) {
        try {
            $user = User::findOrFail($id);

            if (! $this->permissions->canEditUser(auth()->user(), $user)) {
                return response()->json(['message' => 'Access denied'], 403);
            }
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
                'is_active' => 'sometimes|boolean',
            ]);

            $user = User::findOrFail($id);

            if (! $this->permissions->canEditUser(auth()->user(), $user)) {
                return response()->json(['message' => 'Access denied'], 403);
            }

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

    public function fetchLoggedInUser() {
        $user = auth()->user();
        return response()->json(['user' => $user], 200);
    }

    public function editLoggedInUser(Request $request) {
        try {
            $user = auth()->user();

            $validatedData = $request->validate([
                'username' => 'sometimes|required|string|max:255|unique:users,username,' . $user->id,
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
                'password' => 'sometimes|required|string|min:8',
                'first_name' => 'sometimes|required|string|max:100',
                'last_name' => 'sometimes|required|string|max:100',
                'middle_name' => 'sometimes|nullable|string|max:100',
                'suffix' => 'sometimes|nullable|string|max:50',
            ]);

            if (isset($validatedData['password'])) {
                $validatedData['password'] = bcrypt($validatedData['password']);
            }

            $user->update($validatedData);

            return response()->json(['message' => 'User profile updated successfully', 'user' => $user], 200);
        } catch (\Exception $e) {
            Log::error('Error updating logged-in user profile: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update user profile', 'error' => $e->getMessage()], 500);
        }
    }

    public function createAdmin(Request $request) {
        try {
            $actor = auth()->user();

            // allow super_admin always; branch_admin may create admins for their own branch
            if (! $this->permissions->isSuperAdmin($actor)) {
                if ($actor->role !== 'branch_admin') {
                    return response()->json(['message' => 'Access denied: Only Super Admin or Branch Admin can create admins.'], 403);
                }
                // branch_admin: require branch_id in payload and it must match actor's branch
                $branchId = $request->input('branch_id');
                if (! $branchId || (int)$branchId !== (int)$actor->branch_id) {
                    return response()->json(['message' => 'Access denied: Branch Admin may only create admins for their branch.'], 403);
                }
            }

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
                'is_active' => 'sometimes|boolean',
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
                'is_active' => $validatedData['is_active'] ?? true,
            ]);
            return response()->json(['message' => 'Admin user created successfully', 'user' => $user], 201);
        } catch (\Exception $e) {
            Log::error('Error creating admin user: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create admin user', 'error' => $e->getMessage()], 500);
        }
    }
}
