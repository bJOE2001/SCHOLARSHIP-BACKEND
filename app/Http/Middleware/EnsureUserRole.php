<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $allowedRoles = array_values(array_filter(array_map('trim', $roles)));

        if ($allowedRoles !== [] && ! in_array($user->role, $allowedRoles, true)) {
            abort(403, 'You do not have access to this resource.');
        }

        return $next($request);
    }
}
