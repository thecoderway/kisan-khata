<?php

namespace App\Infrastructure\Auth;

use App\Contracts\MobileOtpSender;
use App\Exceptions\MobileOtpDeliveryUnavailable;

class NullMobileOtpSender implements MobileOtpSender
{
    public function send(string $mobile, string $code): void
    {
        throw new MobileOtpDeliveryUnavailable(
            'Mobile OTP delivery is not configured. Configure an SMS provider before enabling OTP login.'
        );
    }
}
