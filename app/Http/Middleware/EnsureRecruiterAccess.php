<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A recruiter login is only useful while the recruiter row AND their company are active.
 * Runs after `role:recruiter`; puts the Recruiter on the request for the controllers.
 */
class EnsureRecruiterAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $recruiter = $request->user()?->recruiter()->with('company')->first();

        abort_unless($recruiter !== null && $recruiter->canAccess(), 403, 'Your recruiter access is not active.');

        $request->attributes->set('recruiter', $recruiter);

        return $next($request);
    }
}
