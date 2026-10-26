<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleSelected
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            if (empty($user->role) && ! $request->routeIs('role.choice*', 'logout', 'api.*') && ! $request->is('api/*') && ! $request->expectsJson()) {
                return redirect()->route('role.choice');
            }
        }

        return $next($request);
    }
}
