<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBranchIp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $userIP = $request->ip();

        if ($user->branch->public_ip !== $userIP) {
            return response()->json(['message' => 'Access denied: You are not allowed to login from this location.'], 403);
        }
        return $next($request);
    }
}
