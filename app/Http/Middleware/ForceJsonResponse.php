<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        // Force Laravel to treat the client as expecting JSON
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
