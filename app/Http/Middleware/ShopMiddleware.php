<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShopMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
     public function handle(Request $request, Closure $next): Response
{
    /** @var \App\Models\User $user */
    $user = $request->user();

    if (!$user || $user->role !== 'shop') {
        abort(403, 'Unauthorized');
    }

    return $next($request);
}
}
