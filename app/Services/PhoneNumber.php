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

    public static function parse(?string $raw, string $defaultCountryCode = '62'): ?string
    {
        if (empty($raw) || trim($raw) === '-') {
            return null;
        }

        $raw = trim($raw);

        // If starts with +, keep + and strip non-digits after it
        if (str_starts_with($raw, '+')) {
            $digits = preg_replace('/\D+/', '', $raw);

            return $digits !== '' ? '+'.$digits : null;
        }

        // Strip all non-digit characters
        $digits = preg_replace('/\D+/', '', $raw);

        if (empty($digits)) {
            return null;
        }

        // If starts with 08... (Indonesian local standard format)
        if (str_starts_with($digits, '08')) {
            return '+'.preg_replace('/^08/', $defaultCountryCode.'8', $digits);
        }

        // If starts with 0... (general local number with leading zero)
        if (str_starts_with($digits, '0')) {
            return '+'.$defaultCountryCode.substr($digits, 1);
        }

        // If starts with country code directly e.g. 628...
        if (str_starts_with($digits, $defaultCountryCode)) {
            return '+'.$digits;
        }

        // If starts with 8... (omitted leading 0 e.g. 8123456789)
        if (str_starts_with($digits, '8') && $defaultCountryCode === '62') {
            return '+'.$defaultCountryCode.$digits;
        }

        return '+'.$defaultCountryCode.$digits;
    }

    public static function whatsappDigits(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        return $digits !== '' ? $digits : null;
    }
}
