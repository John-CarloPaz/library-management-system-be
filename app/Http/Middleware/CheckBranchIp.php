<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if (auth()->check() && auth()->user()->role === 'super_admin') {
            return $next($request);
        }
        
        $user = auth()->user();
        $ip = $request->ip();
    
        $branchByIp = Branch::where('public_ip', $ip)->first();

        if ($branchByIp && $branchByIp->id !== $user->branch_id) {
            return response()->json(['message' => 'Access denied: cannot login from this branch'], 403);
        }

        return $next($request);
    }
}
