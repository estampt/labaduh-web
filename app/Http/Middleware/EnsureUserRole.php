<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        $role = (string) ($user->role ?? '');
        $allowed = collect($roles)->map(fn($r) => (string)$r)->all();

        if (!in_array($role, $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}
