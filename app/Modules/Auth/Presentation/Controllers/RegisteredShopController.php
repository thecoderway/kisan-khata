<?php

namespace App\Modules\Auth\Presentation\Controllers;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredShopController
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\\-\\s]{8,20}$/', 'unique:users,mobile'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $shop = Shop::create([
                'name' => $validated['shop_name'],
                'owner_name' => $validated['owner_name'],
                'mobile' => $validated['mobile'],
                'email' => $validated['email'],
                'preferred_language' => 'hi',
                'timezone' => 'Asia/Kolkata',
            ]);

            $user = new User;
            $user->forceFill([
                'shop_id' => $shop->id,
                'name' => $validated['owner_name'],
                'email' => $validated['email'],
                'mobile' => $validated['mobile'],
                'password' => $validated['password'],
                'role' => User::ROLE_OWNER,
                'preferred_language' => 'hi',
                'is_active' => true,
            ]);
            $user->save();

            DB::table('user_audit_logs')->insert([
                'shop_id' => $shop->getKey(),
                // The initial Owner is the actor for the self-registration event.
                'actor_user_id' => $user->getKey(),
                'target_user_id' => $user->getKey(),
                'action' => 'user.created',
                'old_values' => null,
                'new_values' => json_encode([
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                ], JSON_THROW_ON_ERROR),
                'reason' => 'Initial shop registration',
                'created_at' => now(),
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'आपकी दुकान का खाता सफलतापूर्वक बन गया है।');
    }
}
