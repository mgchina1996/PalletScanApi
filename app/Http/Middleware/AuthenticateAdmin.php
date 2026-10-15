<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('admin_authenticated')) {
            return $request->expectsJson()
                ? response()->json(['message' => '登录已过期，请重新登录。'], 401)
                : redirect()->route('admin.login');
        }

        return $next($request);
    }
}
