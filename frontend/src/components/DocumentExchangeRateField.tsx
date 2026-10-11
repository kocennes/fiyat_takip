/**
 * BISAVUNMA Fiyat Takip: the "Döviz Kuru" input of the document editors (quote/proforma,
 * invoice, recurring invoice, credit, purchase order).
 *
 * It keeps the upstream meaning of `exchange_rate` (document-currency units per 1
 * company-currency unit, see common/helpers/product-currency.ts) and stays editable whenever
 * the document currency is not the company currency; a hint shows the rate both ways.
 * A company-currency (TL) document with rate 1 needs no rate, so the field is hidden for it.
 */

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useColorScheme } from '$app/common/colors';
import { isCompanyCurrency } from '$app/common/helpers/product-currency';
import { useCurrentCompany } from '$app/common/hooks/useCurrentCompany';
import {
  useCurrencyLabel,
  useFormatExchangeRate,
} from '$app/common/hooks/useFormatExchangeRate';
import {
  DocumentCurrencySource,
  useResolveDocumentCurrency,
} from '$app/common/hooks/useResolveDocumentCurrency';
import { RelationType } from '$app/pages/invoices/common/components/ProductsTable';
import { NumberInputField } from './forms/NumberInputField';

interface Props {
  resource: DocumentCurrencySource | undefined;
  relationType: RelationType;
  onChange: (exchangeRate: number) => unknown;
  errorMessage?: string | string[];
}

export function DocumentExchangeRateField(props: Props) {
  const [t] = useTranslation();

  const colors = useColorScheme();
  const company = useCurrentCompany();
  const currencyLabel = useCurrencyLabel();
  const formatExchangeRate = useFormatExchangeRate();
  const resolveDocumentCurrency = useResolveDocumentCurrency();

  const { resource, relationType } = props;

  // undefined while the client / vendor currency is being resolved.
  const [currencyId, setCurrencyId] = useState<string>();

  useEffect(() => {
    let isCurrent = true;

    resolveDocumentCurrency(resource, relationType).then((currency) => {
      if (isCurrent) {
        setCurrencyId(String(currency.currencyId ?? ''));
      }
    });

    return () => {
      isCurrent = false;
    };
  }, [resource?.client_id, resource?.vendor_id, relationType]);

  const companyCurrencyId = company?.settings.currency_id;
  const rate = Number(resource?.exchange_rate ?? 1);

  const isForeignCurrency =
    typeof currencyId === 'string' &&
    !isCompanyCurrency(currencyId, companyCurrencyId);

  if (typeof currencyId === 'string' && !isForeignCurrency && rate === 1) {
    return null;
  }

  const documentLabel = currencyLabel(currencyId);
  const companyLabel = currencyLabel(companyCurrencyId);

  return (
    <div className="space-y-1">
      <NumberInputField
        label={t('exchange_rate')}
        value={resource?.exchange_rate || 1.0}
        onValueChange={(value) => props.onChange(parseFloat(value) || 1.0)}
        errorMessage={props.errorMessage}
        disablePrecision
      />

      {isForeignCurrency && rate > 0 && (
        <p className="text-xs" style={{ color: colors.$22, opacity: 0.8 }}>
          {`1 ${companyLabel} = ${formatExchangeRate(rate)} ${documentLabel}`}
          {' · '}
          {`1 ${documentLabel} = ${formatExchangeRate(1 / rate, 4)} ${companyLabel}`}
        </p>
      )}
    </div>
  );
}
