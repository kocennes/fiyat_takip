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

namespace App\Import\Transformer\Csv;

use App\Import\Transformer\BaseTransformer;

/**
 * Class ProductTransformer.
 */
class ProductTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return array
     */
    public function transform($data)
    {
        $product = [
            'company_id' => $this->company->id,
            'product_key' => $this->getString($data, 'product.product_key'),
            'notes' => $this->getString($data, 'product.notes'),
            'cost' => $this->getFloat($data, 'product.cost'),
            'price' => $this->getFloat($data, 'product.price'),
            'quantity' => $this->getFloat($data, 'product.quantity'),
            'tax_name1' => $this->getString($data, 'product.tax_name1'),
            'tax_rate1' => $this->getFloat($data, 'product.tax_rate1'),
            'tax_name2' => $this->getString($data, 'product.tax_name2'),
            'tax_rate2' => $this->getFloat($data, 'product.tax_rate2'),
            'tax_name3' => $this->getString($data, 'product.tax_name3'),
            'tax_rate3' => $this->getFloat($data, 'product.tax_rate3'),
            'custom_value1' => $this->getCustomFieldValue('product1', $this->getString($data, 'product.custom_value1')),
            'custom_value2' => $this->getCustomFieldValue('product2', $this->getString($data, 'product.custom_value2')),
            'custom_value3' => $this->getCustomFieldValue('product3', $this->getString($data, 'product.custom_value3')),
            'custom_value4' => $this->getCustomFieldValue('product4', $this->getString($data, 'product.custom_value4')),
            'product_image' => $this->getString($data, 'product.image_url'),
            'in_stock_quantity' => $this->getFloat($data, 'product.in_stock_quantity'),
            'max_quantity' => (int) $this->getFloat($data, 'product.max_quantity'),
            'stock_notification' => $this->toBoolean($this->getString($data, 'product.stock_notification')),
            'stock_notification_threshold' => (int) $this->getFloat($data, 'product.stock_notification_threshold'),
            'tax_id' => $this->getNumber($data, 'product.tax_category', 1),
        ];

        return $this->transformCurrency($data, $product);
    }

    /**
     * BISAVUNMA product pricing: the optional currency and exchange rate columns.
     *
     * They are only written when they are mapped, so an import without them
     * keeps the stored currency / rate of existing products.
     * - currency: ISO code (USD), currency id, name or unique symbol (TL);
     *   empty = company currency (TRY). An unknown value is passed through so
     *   the import validation reports the row.
     * - exchange rate: 1 unit of the product currency = rate TRY, "41,5321" or
     *   "41.5321". Always 1 for the company currency; a foreign currency without
     *   a usable rate keeps the rate of the existing product with the same
     *   currency, otherwise the row fails validation (rate required).
     *
     * @param  array<string, mixed> $data
     * @param  array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function transformCurrency(array $data, array $product): array
    {
        $has_currency = array_key_exists('product.currency_id', $data);
        $has_rate = array_key_exists('product.exchange_rate', $data);

        if (! $has_currency && ! $has_rate) {
            return $product;
        }

        $company_currency_id = (string) $this->company->settings->currency_id;

        $existing = strlen((string) $product['product_key']) > 0 ? $this->getProduct($product['product_key']) : null;

        if ($has_currency) {
            $product['currency_id'] = $this->resolveCurrencyId($this->getString($data, 'product.currency_id'));
            $currency_id = (string) $product['currency_id'];
        } else {
            $currency_id = $existing ? trim((string) $existing->currency_id) : '';
        }

        $raw_rate = $has_rate ? trim((string) $data['product.exchange_rate']) : '';

        if ($currency_id === '' || $currency_id === $company_currency_id) {
            $product['exchange_rate'] = 1;
        } elseif ($raw_rate !== '') {
            $rate = $this->parseExchangeRate($raw_rate);
            $product['exchange_rate'] = $rate > 0 ? $rate : null;
        } elseif ($existing && trim((string) $existing->currency_id) === $currency_id && (float) $existing->exchange_rate > 0) {
            $product['exchange_rate'] = (float) $existing->exchange_rate;
        } else {
            $product['exchange_rate'] = null;
        }

        return $product;
    }

    private function resolveCurrencyId(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        /** @var \Illuminate\Support\Collection<int, \App\Models\Currency> $currencies */
        $currencies = app('currencies');

        $needle = mb_strtolower($value);

        $currency = $currencies->first(fn ($currency): bool => (string) $currency->id === $value)
            ?? $currencies->first(fn ($currency): bool => mb_strtolower((string) $currency->code) === $needle)
            ?? $currencies->first(fn ($currency): bool => mb_strtolower((string) $currency->name) === $needle);

        if (! $currency) {
            $by_symbol = $currencies->filter(fn ($currency): bool => mb_strtolower(trim((string) $currency->symbol)) === $needle);
            $currency = $by_symbol->count() === 1 ? $by_symbol->first() : null;
        }

        return $currency ? (string) $currency->id : $value;
    }

    private function parseExchangeRate(string $value): float
    {
        $value = preg_replace('/[^0-9.,]/', '', $value) ?? '';

        $last_comma = strrpos($value, ',');
        $last_dot = strrpos($value, '.');

        if ($last_comma !== false && ($last_dot === false || $last_comma > $last_dot)) {
            // Decimal comma: "41,5321" or "1.234,5678".
            $value = str_replace(['.', ','], ['', '.'], $value);
        } else {
            // Decimal point: "41.5321" or "1,234.5678".
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
