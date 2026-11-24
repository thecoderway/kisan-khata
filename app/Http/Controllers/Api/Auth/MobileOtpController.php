<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\MobileOtpDeliveryUnavailable;
use App\Http\Controllers\Controller;
use App\Services\Auth\MobileOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Illuminate\Validation\Rules\Password as PasswordRule;

class MobileOtpController extends Controller
{
    public function request(Request $request, MobileOtpService $otp): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\\-\\s]{8,20}$/'],
        ]);

        $mobile = trim($validated['mobile']);
        $mobileKey = hash('sha256', $this->mobileRateLimitIdentity($mobile));
        $ipKey = $request->ip() ?? 'unknown';
        $window = (int) config('mobile_otp.request_limit_window_seconds', 900);
        $mobileLimit = (int) config('mobile_otp.request_limit_per_mobile', 3);
        $ipLimit = (int) config('mobile_otp.request_limit_per_ip', 10);

        if (RateLimiterFacade::tooManyAttempts('mobile-otp:request:mobile:'.$mobileKey, $mobileLimit)
            || RateLimiterFacade::tooManyAttempts('mobile-otp:request:ip:'.$ipKey, $ipLimit)) {
            return response()->json(['message' => 'बहुत बार अनुरोध किया गया। कृपया कुछ समय बाद दोबारा प्रयास करें।'], 429);
        }

        RateLimiterFacade::hit('mobile-otp:request:mobile:'.$mobileKey, $window);
        RateLimiterFacade::hit('mobile-otp:request:ip:'.$ipKey, $window);

        try {
            $otp->request($mobile);
        } catch (MobileOtpDeliveryUnavailable) {
            return response()->json([
                'message' => 'OTP सेवा अभी उपलब्ध नहीं है। कृपया बाद में प्रयास करें।',
            ], 503);
        }

        return response()->json([
            'message' => 'यदि यह मोबाइल नंबर सक्रिय खाते से जुड़ा है, तो OTP भेज दिया जाएगा।',
        ], 202);
    }

    public function verify(Request $request, MobileOtpService $otp): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\\-\\s]{8,20}$/'],
            'otp' => ['required', 'digits:6'],
        ]);

        $mobile = trim($validated['mobile']);
        $key = 'mobile-otp:verify:'.hash('sha256', $this->mobileRateLimitIdentity($mobile));
        $limit = (int) config('mobile_otp.verify_limit_per_mobile', 10);

        if (RateLimiterFacade::tooManyAttempts($key, $limit)) {
            return response()->json(['message' => 'बहुत बार प्रयास किया गया। कृपया कुछ समय बाद दोबारा प्रयास करें।'], 429);
        }

        RateLimiterFacade::hit($key, (int) config('mobile_otp.expiry_minutes', 5) * 60);

        $user = $otp->verify($mobile, $validated['otp']);
        $isFirstLogin = $user->must_change_password;
        $expiresAt = $isFirstLogin
            ? now()->addMinutes(10)
            : now()->addDays(max(1, (int) config('mobile_otp.token_expiry_days', 30)));
        $token = $user->createToken(
            $isFirstLogin ? 'mobile-first-login' : 'mobile-app',
            [$isFirstLogin ? 'first-login' : 'app'],
            $expiresAt,
        );

        RateLimiterFacade::clear($key);

        if ($isFirstLogin) {
            return response()->json([
                'message' => 'मोबाइल सत्यापन सफल हुआ। आगे बढ़ने के लिए अस्थायी पासवर्ड सत्यापित करें और नया पासवर्ड बनाएं।',
                'first_login_required' => true,
                'token_type' => 'Bearer',
                'token' => $token->plainTextToken,
                'expires_at' => $expiresAt->toISOString(),
            ]);
        }

        return response()->json([
            'message' => 'लॉगिन सफल हुआ।',
            'first_login_required' => false,
            'token_type' => 'Bearer',
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toISOString(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'role' => $user->role,
                'shop_id' => $user->shop_id,
                'shop' => [
                    'id' => $user->shop->id,
                    'name' => $user->shop->name,
                ],
            ],
        ]);
    }

    public function completeFirstLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'temporary_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();

        if (! $user->must_change_password || ! \Illuminate\Support\Facades\Hash::check($validated['temporary_password'], $user->password)) {
            return response()->json(['message' => 'अस्थायी पासवर्ड सही नहीं है या first-login प्रक्रिया लागू नहीं है।'], 422);
        }

        $expiresAt = now()->addDays(max(1, (int) config('mobile_otp.token_expiry_days', 30)));

        $token = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user, $validated, $expiresAt) {
            $lockedUser = \App\Models\User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (! $lockedUser->must_change_password
                || ! \Illuminate\Support\Facades\Hash::check($validated['temporary_password'], $lockedUser->password)) {
                return null;
            }

            $lockedUser->forceFill([
                'password' => $validated['password'],
                'must_change_password' => false,
            ])->save();

            $request->user()->currentAccessToken()?->delete();

            return $lockedUser->createToken('mobile-app', ['app'], $expiresAt);
        });

        if (! $token) {
            return response()->json(['message' => 'First-login प्रक्रिया पहले ही पूरी हो चुकी है।'], 409);
        }

        $user = $user->fresh()->load('shop');

        return response()->json([
            'message' => 'पासवर्ड सफलतापूर्वक अपडेट हुआ। लॉगिन सफल है।',
            'first_login_required' => false,
            'token_type' => 'Bearer',
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toISOString(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'role' => $user->role,
                'shop_id' => $user->shop_id,
                'shop' => ['id' => $user->shop->id, 'name' => $user->shop->name],
            ],
        ]);
    }

    private function mobileRateLimitIdentity(string $mobile): string
    {
        return preg_replace('/[\s()\-]/u', '', trim($mobile)) ?? trim($mobile);
    }
}
