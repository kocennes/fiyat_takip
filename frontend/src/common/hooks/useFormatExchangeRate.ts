/**
 * BISAVUNMA Fiyat Takip: display helpers for manual exchange rates ("1 USD = 34,5000 TL").
 */

import { numberFormat } from '$app/common/helpers/number-format';
import { roundToPrecision } from '$app/common/helpers/invoices/round';
import { useCurrentCompany } from './useCurrentCompany';
import { useResolveCountry } from './useResolveCountry';
import { useResolveCurrency } from './useResolveCurrency';

/** Short currency label: "TL" for the Turkish lira, the ISO code otherwise ('' = company currency). */
export function useCurrencyLabel() {
  const company = useCurrentCompany();
  const resolveCurrency = useResolveCurrency();

  return (currencyId: string | number | null | undefined) => {
    const currency = resolveCurrency(
      currencyId || company?.settings.currency_id || ''
    );

    if (!currency) {
      return '';
    }

    return currency.code === 'TRY' ? 'TL' : currency.code;
  };
}

/** Formats a rate with the company separators: 2 to `maxDecimals` decimals (34,50 / 0,028986). */
export function useFormatExchangeRate() {
  const company = useCurrentCompany();
  const resolveCountry = useResolveCountry();
  const resolveCurrency = useResolveCurrency();

  return (value: number | string, maxDecimals = 6) => {
    const number = Number(value);

    if (!Number.isFinite(number)) {
      return '';
    }

    const country = resolveCountry(company?.settings.country_id);
    const currency = resolveCurrency(company?.settings.currency_id);

    const rounded = roundToPrecision(number, maxDecimals);
    const decimals = (String(rounded).split('.')[1] ?? '').length;

    return numberFormat(
      rounded,
      Math.min(Math.max(decimals, 2), maxDecimals),
      country?.decimal_separator || currency?.decimal_separator || '.',
      country?.thousand_separator || currency?.thousand_separator || ','
    );
  };
}
