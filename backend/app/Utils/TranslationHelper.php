<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils;

use Illuminate\Support\Facades\Cache;
use App\Models\PaymentTerm;
use Illuminate\Support\Str;

class TranslationHelper
{
    /** BISAVUNMA: id of Turkey in the countries table (ISO 3166-1 numeric code 792). */
    public const TURKEY_ID = 792;

    /**
     * Sorted country lists of this process, per locale.
     *
     * @var array<string, \Illuminate\Support\Collection<int, \App\Models\Country>>
     */
    private static array $sorted_countries = [];

    // public static function getIndustries()
    // {

    //     /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
    //     $industries = app('industries');

    //     return $industries->each(function ($industry) {
    //         $industry->name = ctrans('texts.industry_'.$industry->name);
    //     })->sortBy(function ($industry) {
    //         return $industry->name;
    //     });
    // }

    /**
     * BISAVUNMA: the countries for every backend-rendered country select (client and vendor
     * portal, billing portal, Livewire required fields): Turkey first, then the others in
     * Turkish alphabetical order of their translated names.
     *
     * The shared app('countries') models are returned unchanged (still an Eloquent
     * collection, so Livewire can keep it as a property); views print the translated
     * name with $country->getName().
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Country>
     */
    public static function getCountries()
    {
        $locale = (string) app()->getLocale();

        if (! isset(self::$sorted_countries[$locale])) {
            /** @var \Illuminate\Support\Collection<int, \App\Models\Country> $countries */
            $countries = app('countries');

            self::$sorted_countries[$locale] = self::sortCountries($countries, function ($country): string {
                return $country->getName();
            });
        }

        return self::$sorted_countries[$locale];
    }

    /**
     * BISAVUNMA: orders countries with Turkey first and the rest in Turkish alphabetical
     * order (a b c ç d e f g ğ h ı i j k l m n o ö p r s ş t u ü v y z) of $label($country).
     * Returns a new collection of the same class; the models are not modified.
     *
     * @template TCollection of \Illuminate\Support\Collection
     * @param  TCollection $countries
     * @param  callable(\App\Models\Country): string $label The name shown to the user.
     * @return TCollection
     */
    public static function sortCountries($countries, callable $label)
    {
        $collator = self::turkishCollator();
        $keys = [];

        foreach ($countries as $country) {
            $keys[$country->id] = self::turkishSortKey((string) $label($country), $collator);
        }

        return $countries->sort(function ($a, $b) use ($keys): int {
            $a_is_turkey = (int) $a->id === self::TURKEY_ID;
            $b_is_turkey = (int) $b->id === self::TURKEY_ID;

            if ($a_is_turkey !== $b_is_turkey) {
                return $a_is_turkey ? -1 : 1;
            }

            return strcmp($keys[$a->id], $keys[$b->id]);
        })->values();
    }

    /**
     * A byte string whose strcmp() order is the Turkish alphabetical order of $value.
     */
    private static function turkishSortKey(string $value, ?\Collator $collator): string
    {
        if ($collator !== null) {
            $key = $collator->getSortKey($value);

            if (is_string($key)) {
                return $key;
            }
        }

        // Without the intl extension: each Turkish letter sorts right after its base letter
        // (c < ç < d, g < ğ < h, h < ı < i, o < ö < p, s < ş < t, u < ü < v), "I" is the capital
        // of "ı" and "İ" the capital of "i"; other accents are ignored (é = e, å = a).
        $value = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $value), 'UTF-8');
        $value = strtr($value, ['ç' => 'c~', 'ğ' => 'g~', 'ı' => 'h~', 'ö' => 'o~', 'ş' => 's~', 'ü' => 'u~']);

        return Str::ascii($value) . "\0" . $value;
    }

    private static function turkishCollator(): ?\Collator
    {
        if (! class_exists(\Collator::class)) {
            return null;
        }

        try {
            return new \Collator('tr_TR');
        } catch (\Throwable) {
            return null;
        }
    }

    // public static function getPaymentTypes()
    // {

    //     /** @var \Illuminate\Support\Collection<\App\Models\PaymentType> */
    //     // $payment_types = app('payment_types');

    //     return \App\Models\PaymentType::all()->each(function ($pType) {
    //         $pType->name = ctrans('texts.payment_type_'.$pType->name);
    //     })->sortBy(function ($pType) {
    //         return $pType->name;
    //     });
    // }

    // public static function getLanguages()
    // {

    //     /** @var \Illuminate\Support\Collection<\App\Models\Language> */
    //     // $languages = app('languages');

    //     return \App\Models\Language::all()->each(function ($lang) {
    //         $lang->name = ctrans('texts.lang_'.$lang->name);
    //     })->sortBy(function ($lang) {
    //         return $lang->name;
    //     });
    // }

    public static function getCurrencies()
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
        return app('currencies');

    }

    // public static function getPaymentTerms()
    // {
    //     return PaymentTerm::getCompanyTerms()->map(function ($term) {
    //         $term['name'] = ctrans('texts.payment_terms_net').' '.$term['num_days'];

    //         return $term;
    //     });
    // }
}
