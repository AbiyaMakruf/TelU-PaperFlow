<?php

namespace App\Services;

class PhoneNumber
{
    public static function normalize(string $countryCode, string $number): string
    {
        $country = preg_replace('/\D+/', '', $countryCode);
        $national = ltrim((string) preg_replace('/\D+/', '', $number), '0');

        return '+'.$country.$national;
    }

    public static function whatsappDigits(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        return $digits !== '' ? $digits : null;
    }
}
