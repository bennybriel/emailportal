<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckAllowedIP
{
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();

        $allowed = DB::table('allowed_ips')
            ->where('ip_address', $ip)
            ->exists();

        if (!$allowed) {
            return response()->json([
                'error' => 'Unauthorized IP address: ' . $ip
            ], 401);
        }

        return $next($request);
    }
}
