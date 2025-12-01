<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecureApiAccess
{
    
    public function handle(Request $request, Closure $next)
    {
        $clientIp = $request->ip();

        // STEP 1: CHECK ALLOWED IP
        $allowed = DB::table('allowed_ips')
                    ->where('ip_address', $clientIp)
                    ->exists();

        if (!$allowed) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized IP address: ' . $clientIp
            ], 401);
        }

        // STEP 2: CHECK API TOKEN
        $token = $request->header('X-API-KEY');

        if (!$token || $token !== env('API_ACCESS_TOKEN')) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or missing API token'
            ], 401);
        }

        return $next($request);
    }
}
