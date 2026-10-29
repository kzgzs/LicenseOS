<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detects credential changes (email/password) and forces logout.
 *
 * When a user's email or password is changed (by admin or via password reset),
 * this middleware detects the mismatch between the session-stored credential
 * fingerprint and the current database values, then invalidates the session.
 *
 * Works with any session driver (file, database, redis, etc.)
 */
class CredentialGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Build current credential fingerprint from DB values
        $currentFingerprint = $this->buildFingerprint($user->email, $user->getAuthPassword());

        // Get the fingerprint stored in the session
        $sessionFingerprint = session('credential_fingerprint');

        if ($sessionFingerprint === null) {
            // First request after login — store the fingerprint
            session(['credential_fingerprint' => $currentFingerprint]);
            return $next($request);
        }

        if (!hash_equals($sessionFingerprint, $currentFingerprint)) {
            // Credentials changed (email or password) — force logout
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Your account credentials were changed. Please sign in again.');
        }

        return $next($request);
    }

    /**
     * Build a deterministic credential fingerprint from email + password hash.
     * Uses SHA-256 (not bcrypt) so the same input always produces the same output.
     */
    private function buildFingerprint(string $email, string $passwordHash): string
    {
        return hash('sha256', $email . '|' . $passwordHash);
    }
}
