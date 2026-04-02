<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckCanManageAdmins
{
    public function __construct(private PermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($this->permissions->canManageAdmins($user)) {
            return $next($request);
        }

        return response()->json(['message' => 'Access denied.'], 403);
    }
}
