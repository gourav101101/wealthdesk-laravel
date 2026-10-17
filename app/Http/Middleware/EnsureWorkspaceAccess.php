<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureWorkspaceAccess
{
    public function handle(Request $request, Closure $next, string $role = 'any')
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        if (! $user->isAdmin() && (! $user->client || $user->client->archived)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'Your account is unavailable. Contact your administrator.']);
        }
        abort_if($role === 'admin' && ! $user->isAdmin(), 403);

        return $next($request);
    }
}
