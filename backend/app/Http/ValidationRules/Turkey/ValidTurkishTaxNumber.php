<?php

/**
 * BISAVUNMA Fiyat Takip - Turkish tax number validation.
 */

namespace App\Http\ValidationRules\Turkey;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The tax number (vat_number) of a client or vendor in Turkey: a 10-digit
 * vergi kimlik numarası (VKN) or an 11-digit T.C. kimlik numarası (TCKN)
 * whose check digits are verified. Spaces, dots, dashes and a leading "TR"
 * are ignored; an empty value is allowed.
 */
class ValidTurkishTaxNumber implements ValidationRule
{
    /** ISO 3166 numeric id of Turkey. */
    public const TURKEY = '792';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) $value)) {
            $fail(ctrans('texts.bis_invalid_turkish_tax_number'));
        }
    }

    /**
     * True when the record's country (or, without one, the company's) is Turkey.
     */
    public static function appliesTo(mixed $country_id, mixed $company_country_id): bool
    {
        $country_id = (string) ($country_id ?? '');

        return ($country_id !== '' ? $country_id : (string) ($company_country_id ?? '')) === self::TURKEY;
    }

    public static function isValid(string $value): bool
    {
        $number = preg_replace('/^TR/i', '', preg_replace('/[\s.\-]+/', '', $value) ?? '') ?? '';

        if ($number === '') {
            return true;
        }

        if (preg_match('/^\d{10}$/', $number) === 1) {
            return true; // VKN
        }

        return preg_match('/^[1-9]\d{10}$/', $number) === 1 && self::isValidTckn($number);
    }

    /**
     * TCKN check digits: the 10th digit is ((sum of digits 1,3,5,7,9) × 7 − sum of
     * digits 2,4,6,8) mod 10, the 11th digit is the sum of the first ten mod 10.
     */
    public static function isValidTckn(string $tckn): bool
    {
        $d = array_map('intval', str_split($tckn));

        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];

        if (((($odd * 7) - $even) % 10 + 10) % 10 !== $d[9]) {
            return false;
        }

        return array_sum(array_slice($d, 0, 10)) % 10 === $d[10];
    }
}
