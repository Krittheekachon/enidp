<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            if ($request->header('X-Inertia')) {
                return redirect()->route('login');
            }

            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (! auth()->user()->hasRole($roles)) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors([
                    'authorization' => 'คุณไม่มีสิทธิ์ดำเนินการนี้',
                ]);
            }

            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
