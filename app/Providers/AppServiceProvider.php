<?php

namespace App\\Providers;

use App\\Contracts\\MobileOtpSender;
use App\\Infrastructure\\Auth\\NullMobileOtpSender;
use App\\Tenancy\\CurrentShop;
use Illuminate\\Support\\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentShop::class, fn (): CurrentShop => new CurrentShop);

        $this->app->bind(MobileOtpSender::class, function (): MobileOtpSender {
            $sender = config('mobile_otp.sender');

            if (is_string($sender) && class_exists($sender) && is_subclass_of($sender, MobileOtpSender::class)) {
                return app($sender);
            }

            return new NullMobileOtpSender;
        });
    }

    public function boot(): void
    {
        //
    }
}
