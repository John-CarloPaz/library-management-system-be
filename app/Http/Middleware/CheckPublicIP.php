<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use App\Models\User;
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

        // Find user by provided email in request
        $user = User::where('email', $request->email)->first();
        // Defensive checks
        if (! $user) {
            return response()->json(['message' => 'Access denied: user not found'], 403);
        }

        $ip = $request->ip();

         // Super admins are exempted
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // If this IP is registered to another branch, prevent login from that branch
        $branchByIp = Branch::where('public_ip', $ip)
            ->orWhere('public_ipv6', $ip)
            ->first();

        if ($branchByIp && $branchByIp->id !== $user->branch_id) {
            return response()->json(['message' => 'Access denied: cannot login from this branch'], 403);
        }

        $branch = $user->branch;
        if (! $branch) {
            return response()->json(['message' => 'Access denied: branch not configured'], 403);
        }

        // Collect configured allowed IPs (ignore empty values)
        $allowed = array_values(array_filter([
            $branch->public_ip ?? null,
            $branch->public_ipv6 ?? null,
        ], fn($v) => $v !== null && $v !== ''));

        if (empty($allowed)) {
            return response()->json(['message' => 'Access denied: no branch public IP configured'], 403);
        }

        // Allow if client IP matches any configured branch IP (either IPv4 or IPv6)
        if (! in_array($ip, $allowed, true)) {
            return response()->json(['message' => 'Access denied: Unauthorized IP address'], 403);
        }

        return $next($request);
    }
}
