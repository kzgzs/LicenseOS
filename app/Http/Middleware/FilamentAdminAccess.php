<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FilamentAdminAccess
{
    /**
     * Only allow super_admin and moderator roles to access the Filament admin panel.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        // Only super_admin and moderator can access the admin panel
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $next($request);
        }

        if (method_exists($user, 'isModerator') && $user->isModerator()) {
            return $next($request);
        }

        abort(403, 'You do not have permission to access the admin panel.');
    }
}
