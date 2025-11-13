<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckSuperAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        return response()->json(['message' => 'Access denied. Only super_admins are allowed.'], 403);
    }
}
