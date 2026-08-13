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

        // 1. If starts with +, preserve international prefix and strip non-digits
        if (str_starts_with($raw, '+')) {
            $digits = preg_replace('/\D+/', '', $raw);

            return $digits !== '' ? '+'.$digits : null;
        }

        // Strip non-digit characters
        $digits = preg_replace('/\D+/', '', $raw);

        if (empty($digits)) {
            return null;
        }

        // 2. If starts with international 00 prefix (e.g. 0060123456789, 006591234567)
        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        // 3. If starts with 08... (Indonesian local standard format)
        if (str_starts_with($digits, '08')) {
            return '+'.preg_replace('/^08/', $defaultCountryCode.'8', $digits);
        }

        // 4. If starts with 8... (Indonesian local number omitting leading zero e.g. 81283887102)
        if (str_starts_with($digits, '8') && $defaultCountryCode === '62') {
            return '+'.$defaultCountryCode.$digits;
        }

        // 5. Check for known international country prefixes (e.g. 62=Indonesia, 60=Malaysia, 65=Singapore, 63=Philippines, 66=Thailand, 84=Vietnam, 673=Brunei, 61=Australia, 1=USA, 44=UK, 91=India, etc.)
        $knownCountryPrefixes = ['62', '60', '65', '63', '66', '84', '673', '61', '1', '44', '91', '86', '49', '33'];
        foreach ($knownCountryPrefixes as $prefix) {
            if (str_starts_with($digits, $prefix) && strlen($digits) >= (strlen($prefix) + 7)) {
                return '+'.$digits;
            }
        }

        // 6. If starts with 0... (general local number with leading zero)
        if (str_starts_with($digits, '0')) {
            return '+'.$defaultCountryCode.substr($digits, 1);
        }

        return '+'.$defaultCountryCode.$digits;
    }

    public static function whatsappDigits(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        return $digits !== '' ? $digits : null;
    }
}
