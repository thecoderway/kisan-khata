<?php

namespace App\\Modules\\Auth\\Presentation\\Controllers;

use Illuminate\\Cache\\RateLimiter;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Support\\Str;
use Illuminate\\Validation\\ValidationException;
use Illuminate\\View\\View;

class AuthenticatedSessionController
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, RateLimiter $limiter): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());

        if ($limiter->tooManyAttempts('login:'.$key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'बहुत बार प्रयास किया गया। कृपया एक मिनट बाद दोबारा प्रयास करें।',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $limiter->hit('login:'.$key, 60);

            throw ValidationException::withMessages([
                'email' => 'ईमेल या पासवर्ड सही नहीं है।',
            ]);
        }

        $user = $request->user();
        if (! $user->is_active || ! $user->shop || $user->shop->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'यह खाता फिलहाल सक्रिय नहीं है।',
            ]);
        }

        $limiter->clear('login:'.$key);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
