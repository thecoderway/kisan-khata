<?php

namespace App\\Services\\Auth;

use App\\Contracts\\MobileOtpSender;
use App\\Models\\Shop;
use App\\Models\\User;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Validation\\ValidationException;

class MobileOtpService
{
    public function __construct(private readonly MobileOtpSender $sender) {}

    public function request(string $mobile): bool
    {
        $user = User::query()->with('shop')->where('mobile', $mobile)->first();

        if (! $this->isEligible($user)) {
            return false;
        }

        $latest = DB::table('mobile_otp_challenges')
            ->where('mobile', $mobile)
            ->latest('id')
            ->first();

        if ($latest && now()->diffInSeconds($latest->created_at) < (int) config('mobile_otp.resend_cooldown_seconds', 60)) {
            return true;
        }

        $code = str_pad(
            (string) random_int(0, (10 ** (int) config('mobile_otp.length', 6)) - 1),
            (int) config('mobile_otp.length', 6),
            '0',
            STR_PAD_LEFT,
        );

        DB::transaction(function () use ($mobile, $code): void {
            DB::table('mobile_otp_challenges')
                ->where('mobile', $mobile)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now(), 'updated_at' => now()]);

            DB::table('mobile_otp_challenges')->insert([
                'mobile' => $mobile,
                'otp_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes((int) config('mobile_otp.expiry_minutes', 5)),
                'consumed_at' => null,
                'attempts' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $this->sender->send($mobile, $code);
        } catch (\\Throwable $exception) {
            DB::table('mobile_otp_challenges')
                ->where('mobile', $mobile)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now(), 'updated_at' => now()]);

            throw $exception;
        }

        return true;
    }

    public function verify(string $mobile, string $code): User
    {
        $result = DB::transaction(function () use ($mobile, $code): array {
            $challenge = DB::table('mobile_otp_challenges')
                ->where('mobile', $mobile)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $challenge) {
                return ['status' => 'invalid'];
            }

            if (now()->greaterThanOrEqualTo($challenge->expires_at)
                || $challenge->attempts >= (int) config('mobile_otp.max_attempts', 5)) {
                DB::table('mobile_otp_challenges')->where('id', $challenge->id)->update([
                    'consumed_at' => now(),
                    'updated_at' => now(),
                ]);

                return ['status' => 'invalid'];
            }

            $attempts = $challenge->attempts + 1;

            if (! Hash::check($code, $challenge->otp_hash)) {
                DB::table('mobile_otp_challenges')->where('id', $challenge->id)->update([
                    'attempts' => $attempts,
                    'consumed_at' => $attempts >= (int) config('mobile_otp.max_attempts', 5) ? now() : null,
                    'updated_at' => now(),
                ]);

                return ['status' => 'invalid'];
            }

            $user = User::query()->with('shop')->where('mobile', $mobile)->first();

            if (! $this->isEligible($user)) {
                DB::table('mobile_otp_challenges')->where('id', $challenge->id)->update([
                    'attempts' => $attempts,
                    'consumed_at' => now(),
                    'updated_at' => now(),
                ]);

                return ['status' => 'inactive'];
            }

            DB::table('mobile_otp_challenges')->where('id', $challenge->id)->update([
                'attempts' => $attempts,
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

            $user->forceFill(['mobile_verified_at' => $user->mobile_verified_at ?? now()])->save();

            return ['status' => 'verified', 'user' => $user];
        });

        if ($result['status'] !== 'verified') {
            throw ValidationException::withMessages([
                'otp' => $result['status'] === 'inactive'
                    ? 'यह खाता फिलहाल सक्रिय नहीं है।'
                    : 'OTP गलत है या उसकी समय-सीमा समाप्त हो गई है।',
            ]);
        }

        return $result['user'];
    }

    private function isEligible(?User $user): bool
    {
        return $user !== null
            && $user->is_active
            && $user->shop !== null
            && $user->shop->status === Shop::STATUS_ACTIVE;
    }
}
