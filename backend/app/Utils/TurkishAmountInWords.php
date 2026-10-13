<?php

/**
 * BISAVUNMA Fiyat Takip - amounts written out in Turkish.
 */

namespace App\Utils;

/**
 * Writes an amount out in Turkish words, as printed on Turkish invoices and
 * proformas ("Yalnız Bin İki Yüz Türk Lirası Elli Kuruş").
 */
class TurkishAmountInWords
{
    private const ONES = ['', 'Bir', 'İki', 'Üç', 'Dört', 'Beş', 'Altı', 'Yedi', 'Sekiz', 'Dokuz'];

    private const TENS = ['', 'On', 'Yirmi', 'Otuz', 'Kırk', 'Elli', 'Altmış', 'Yetmiş', 'Seksen', 'Doksan'];

    private const SCALES = ['', 'Bin', 'Milyon', 'Milyar', 'Trilyon', 'Katrilyon'];

    /** Currency code => [main unit, sub unit]. */
    private const CURRENCIES = [
        'TRY' => ['Türk Lirası', 'Kuruş'],
        'USD' => ['ABD Doları', 'Sent'],
        'EUR' => ['Avro', 'Sent'],
        'GBP' => ['İngiliz Sterlini', 'Peni'],
        'CHF' => ['İsviçre Frangı', 'Santim'],
    ];

    /**
     * The amount in words with its currency, e.g. "Bin İki Yüz Türk Lirası Elli Kuruş".
     * Currencies without a Turkish name use their ISO code ("Yüz SAR 50/100").
     */
    public static function amount(float $amount, ?string $currency_code): string
    {
        $cents = (int) round(abs($amount) * 100);
        $main = intdiv($cents, 100);
        $sub = $cents % 100;

        $code = strtoupper((string) $currency_code);
        [$main_unit, $sub_unit] = self::CURRENCIES[$code] ?? [$code, null];

        $words = trim(self::number($main) . ' ' . $main_unit);

        if ($sub > 0) {
            $words .= $sub_unit === null
                ? " {$sub}/100"
                : ' ' . self::number($sub) . ' ' . $sub_unit;
        }

        return ($amount < 0 && $cents > 0 ? 'Eksi ' : '') . $words;
    }

    /**
     * A whole number in words, e.g. 1001 => "Bin Bir", 2000000 => "İki Milyon".
     */
    public static function number(int $number): string
    {
        $number = abs($number);

        if ($number === 0) {
            return 'Sıfır';
        }

        $groups = [];

        for ($scale = 0; $number > 0 && $scale < count(self::SCALES); $scale++) {
            $chunk = $number % 1000;
            $number = intdiv($number, 1000);

            if ($chunk === 0) {
                continue;
            }

            // "Bin", never "Bir Bin".
            $words = ($scale === 1 && $chunk === 1) ? '' : self::hundreds($chunk);

            array_unshift($groups, trim($words . ' ' . self::SCALES[$scale]));
        }

        return implode(' ', $groups);
    }

    private static function hundreds(int $number): string
    {
        $hundreds = intdiv($number, 100);
        $tens = intdiv($number % 100, 10);
        $ones = $number % 10;

        $words = [];

        if ($hundreds > 0) {
            // "Yüz", never "Bir Yüz".
            $words[] = ($hundreds > 1 ? self::ONES[$hundreds] . ' ' : '') . 'Yüz';
        }

        if ($tens > 0) {
            $words[] = self::TENS[$tens];
        }

        if ($ones > 0) {
            $words[] = self::ONES[$ones];
        }

        return implode(' ', $words);
    }
}
