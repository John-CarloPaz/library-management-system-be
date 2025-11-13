<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPublicIP
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Get the user's public IP
        $userIP = $request->ip();

        // Check if the IP exists in the branches table
        $isAllowed = Branch::where('public_ip', $userIP)->exists();

        if (!$isAllowed) {
            return response()->json(['message' => 'Access denied: Unauthorized IP address'], 403);
        }

        return $next($request);
    }
}
