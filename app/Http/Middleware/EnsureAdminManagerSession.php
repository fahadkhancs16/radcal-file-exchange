<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the staff-access user manager. A single shared password (not tied
 * to any account) unlocks it for the session — see config/admin_manager.php.
 * Deliberately separate from the Filament panel's own auth: this is the
 * recovery path if every admin password is lost.
 */
class EnsureAdminManagerSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('admin_manager.authenticated')) {
            return redirect()->route('admin-manager.gate');
        }

        return $next($request);
    }
}
