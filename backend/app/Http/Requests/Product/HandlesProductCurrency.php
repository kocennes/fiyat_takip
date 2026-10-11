<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Validation\Rule;

/**
 * BISAVUNMA product pricing input, shared by the product store/update requests.
 *
 * - currency_id:   currency of BOTH cost (alış fiyatı) and price (satış fiyatı);
 *                  null / '' = company currency (TRY). Must be an existing currency id.
 * - exchange_rate: manual rate, 1 unit of the product currency = exchange_rate TRY.
 *                  Numeric and > 0; always 1 for a company-currency product.
 *                  Required when a product gets a foreign currency (create, or a
 *                  currency change on update).
 */
trait HandlesProductCurrency
{
    /**
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function normalizeCurrencyInput(array $input, mixed $product = null): array
    {
        $product = $product instanceof Product ? $product : null;

        if (array_key_exists('currency_id', $input)) {
            $currency_id = $input['currency_id'];

            if (is_int($currency_id) || is_float($currency_id)) {
                $currency_id = (string) $currency_id;
            }

            if (is_string($currency_id)) {
                $currency_id = trim($currency_id);
            }

            $input['currency_id'] = ($currency_id === '' || $currency_id === null) ? null : $currency_id;
        }

        if (array_key_exists('exchange_rate', $input) && is_string($input['exchange_rate'])) {
            $rate = trim($input['exchange_rate']);

            // Accept a Turkish decimal comma, e.g. "41,5321".
            if (str_contains($rate, ',') && ! str_contains($rate, '.')) {
                $rate = str_replace(',', '.', $rate);
            }

            $input['exchange_rate'] = $rate;
        }

        $currency_id = $this->effectiveCurrencyId($input, $product);

        if ($currency_id !== null && $this->isCompanyCurrency($currency_id)) {
            $input['exchange_rate'] = 1;
        } elseif (array_key_exists('exchange_rate', $input) && ($input['exchange_rate'] === null || $input['exchange_rate'] === '')) {
            // No rate given: keep the stored rate (update) or let the rules require one.
            unset($input['exchange_rate']);
        }

        return $input;
    }

    /**
     * @param  array<string, mixed> $rules
     * @return array<string, mixed>
     */
    protected function currencyRules(array $rules, mixed $product = null): array
    {
        $product = $product instanceof Product ? $product : null;

        $rules['currency_id'] = ['bail', 'sometimes', 'nullable', 'string', 'regex:/^[0-9]+$/', Rule::exists('currencies', 'id')];
        $rules['exchange_rate'] = ['bail', $this->exchangeRateRequired($product) ? 'required' : 'sometimes', 'numeric', 'gt:0', 'max:99999999999999'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function currencyAttributes(): array
    {
        return [
            'currency_id' => ctrans('texts.currency'),
            'exchange_rate' => ctrans('texts.exchange_rate'),
        ];
    }

    private function exchangeRateRequired(?Product $product): bool
    {
        $input = $this->all();

        $currency_id = $this->effectiveCurrencyId($input, $product);

        // An invalid currency is reported by the currency_id rule; a company-currency product always has rate 1.
        if ($currency_id === null || $this->isCompanyCurrency($currency_id)) {
            return false;
        }

        if (! $product) {
            return true;
        }

        return array_key_exists('currency_id', $input) && $currency_id !== trim((string) $product->currency_id);
    }

    /**
     * Currency the product will have after this request ('' = company currency, null = invalid input).
     *
     * @param  array<string, mixed> $input
     */
    private function effectiveCurrencyId(array $input, ?Product $product): ?string
    {
        if (array_key_exists('currency_id', $input)) {
            if ($input['currency_id'] === null) {
                return '';
            }

            return is_scalar($input['currency_id']) ? trim((string) $input['currency_id']) : null;
        }

        return $product ? trim((string) $product->currency_id) : '';
    }

    private function isCompanyCurrency(string $currency_id): bool
    {
        if ($currency_id === '') {
            return true;
        }

        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        $company_currency_id = trim((string) ($user?->company()?->settings->currency_id ?? ''));

        return $company_currency_id !== '' && $currency_id === $company_currency_id;
    }
}
