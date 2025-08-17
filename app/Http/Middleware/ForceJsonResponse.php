<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Forcer l'acceptation JSON pour toutes les routes API
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
