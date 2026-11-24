<?php

namespace App\Modules\Auth\Presentation\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ForcePasswordChangeController
{
    public function edit(): View
    {
        return view('auth.force-password-change');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $updatedUser = DB::transaction(function () use ($request, $validated): User {
            $user = User::query()
                ->whereKey($request->user()->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $user->must_change_password
                || ! Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'अस्थायी पासवर्ड सही नहीं है या first-login प्रक्रिया पूरी हो चुकी है।',
                ]);
            }

            $user->forceFill([
                'password' => $validated['password'],
                'must_change_password' => false,
            ])->save();
        });

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'आपका पासवर्ड सफलतापूर्वक अपडेट हो गया है।');
    }
}
