<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsDev
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            abort(403, 'Unauthorized.');
        }

        if (auth()->user()->role !== 'dev') {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
