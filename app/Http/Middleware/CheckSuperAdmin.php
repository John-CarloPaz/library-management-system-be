<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckSuperAdmin
{
    public function __construct(private PermissionService $permissions)
    {
    }
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if ($this->permissions->isSuperAdmin($user)) {
            return $next($request);
        }

        return response()->json(['message' => 'Access denied. Only super_admins are allowed.'], 403);
    }
}
