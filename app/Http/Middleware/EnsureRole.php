<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:tpo,system_admin'). Inactive accounts are refused everywhere. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->is_active, 403, 'Your account is not active.');

        $allowed = array_map(fn (string $r) => Role::from($r), $roles);
        abort_unless($user->hasRole(...$allowed), 403, 'You do not have access to this area.');

        return $next($request);
    }
}
