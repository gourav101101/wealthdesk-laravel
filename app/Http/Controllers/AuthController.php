<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        return auth()->check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function login(Request $request)
    {
        $input = $request->validate(['login' => 'required|string|max:160', 'password' => 'required|string|max:128']);
        $key = 'login:'.hash('sha256', mb_strtolower($input['login']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['login' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        $login = trim($input['login']);
        $user = User::where('email', mb_strtolower($login))->orWhereHas('client', fn ($q) => $q->where('code', strtoupper($login)))->first();
        if (! $user || ! Auth::attempt(['email' => $user->email, 'password' => $input['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages(['login' => 'The email / Client ID or password is incorrect.']);
        }
        $user = Auth::user();
        if (! $user->isAdmin() && (! $user->client || $user->client->archived)) {
            Auth::logout();
            throw ValidationException::withMessages(['login' => 'Your account is unavailable. Contact your administrator.']);
        }
        // Older hashes cannot be reversed. Capture only a successfully verified login.
        if (! $user->isAdmin() && $user->getRawOriginal('client_password') === null) {
            User::whereKey($user->id)->where('password', $user->getAuthPassword())->whereNull('client_password')
                ->update(['client_password' => Crypt::encryptString($input['password'])]);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
