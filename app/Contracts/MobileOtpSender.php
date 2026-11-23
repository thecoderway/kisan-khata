<?php

namespace App\Contracts;

interface MobileOtpSender
{
    public function send(string $mobile, string $code): void;
}
