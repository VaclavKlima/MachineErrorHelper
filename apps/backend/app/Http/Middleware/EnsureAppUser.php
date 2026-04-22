<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole('app_user')) {
            abort(403, 'This account cannot use the app.');
        }

        return $next($request);
    }
}
