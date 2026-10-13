/**
 * BISAVUNMA Fiyat Takip: options of the proforma Excel download.
 *
 * The Excel file mirrors the proforma PDF. Below it the internal profit table lists every line
 * item (quantity, purchase, sale, profit, margin); here the user decides whether the file gets
 * that table (not for files sent to the client) and its currency (TL or USD, or the document
 * currency). The rate typed here fills the editable rate cell of the Excel table, whose formulas
 * convert the amounts between TL and the foreign currency; it may be left empty and entered in
 * Excel instead. Only a third-currency document shown in USD needs its document rate up front.
 */

import { useAtom } from 'jotai';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { trans } from '$app/common/helpers';
import { useCurrentCompany } from '$app/common/hooks/useCurrentCompany';
import { useResolveCurrency } from '$app/common/hooks/useResolveCurrency';
import { useResolveDocumentCurrency } from '$app/common/hooks/useResolveDocumentCurrency';
import { Button, InputField, Radio } from '$app/components/forms';
import Toggle from '$app/components/forms/Toggle';
import { Modal } from '$app/components/Modal';
import {
  proformaExcelAtom,
  useDownloadProformaExcel,
} from '../hooks/useDownloadProformaExcel';

const USD_RATE_STORAGE_KEY = 'bisavunma.proforma_excel.usd_rate';

/** The profit table converts between TL and one foreign currency, whatever the company currency. */
const TL = 'TRY';

const readStoredUsdRate = () => {
  try {
    return window.localStorage.getItem(USD_RATE_STORAGE_KEY) || '';
  } catch {
    return '';
  }
};

const storeUsdRate = (value: number) => {
  try {
    window.localStorage.setItem(USD_RATE_STORAGE_KEY, String(value));
  } catch {
    // Storage can be unavailable (private mode); the rate is then asked again next time.
  }
};

const toRate = (value: string) => {
  const rate = Number(String(value).replace(',', '.'));

  return Number.isFinite(rate) && rate > 0 ? rate : 0;
};

/** "41.2500" for 41.25 (rates are entered with a dot or a comma). */
const formatRate = (rate: number) => (rate > 0 ? rate.toFixed(4) : '');

export function ProformaExcelModal() {
  const [t] = useTranslation();
  const company = useCurrentCompany();

  const [excelRequest, setExcelRequest] = useAtom(proformaExcelAtom);

  const resolveCurrency = useResolveCurrency();
  const resolveDocumentCurrency = useResolveDocumentCurrency();
  const download = useDownloadProformaExcel();

  const companyCode =
    resolveCurrency(company?.settings.currency_id ?? '')?.code || 'TRY';

  const [documentCode, setDocumentCode] = useState(companyCode);
  const [includeProfit, setIncludeProfit] = useState(true);
  const [currency, setCurrency] = useState(companyCode);
  const [documentRate, setDocumentRate] = useState('');
  const [usdRate, setUsdRate] = useState('');
  const [error, setError] = useState('');
  const [isDownloading, setIsDownloading] = useState(false);

  useEffect(() => {
    if (!excelRequest) {
      return;
    }

    resolveDocumentCurrency(excelRequest.entity, 'client_id').then(
      ({ currencyId, exchangeRate }) => {
        const code = resolveCurrency(currencyId ?? '')?.code || companyCode;
        const rate = Number(exchangeRate);

        // The document stores document-currency units per 1 company unit, which is a TL rate
        // only when the company works in TL; 1 means "no rate entered".
        const documentToCompany =
          companyCode === TL &&
          code !== companyCode &&
          rate > 0 &&
          Math.abs(rate - 1) > 0.000001
            ? 1 / rate
            : 0;

        setDocumentCode(code);
        setCurrency(code === 'USD' ? 'USD' : TL);
        setDocumentRate(formatRate(documentToCompany));
        setUsdRate(
          (code === 'USD' && formatRate(documentToCompany)) ||
            readStoredUsdRate()
        );
        setIncludeProfit(true);
        setError('');
      }
    );
  }, [excelRequest]);

  const isThirdCurrency = ![TL, 'USD'].includes(documentCode);
  // A third-currency document (e.g. EUR) shown in USD is converted to TL before the download.
  const needsDocumentRate = isThirdCurrency && currency === 'USD';
  const showDocumentRate = isThirdCurrency;
  const showUsdRate = !isThirdCurrency || currency === 'USD';

  const companyLabel = 'TL';

  const currencyOptions = [
    { id: 'excel-currency-try', title: t('bis_turkish_lira'), value: TL },
    { id: 'excel-currency-usd', title: t('bis_us_dollar'), value: 'USD' },
    ...(isThirdCurrency
      ? [
          {
            id: 'excel-currency-document',
            title: trans('bis_excel_document_currency', {
              currency: documentCode,
            }),
            value: documentCode,
          },
        ]
      : []),
  ];

  const close = () => {
    setExcelRequest(null);
    setIsDownloading(false);
  };

  const handleDownload = () => {
    if (!excelRequest) {
      return;
    }

    const parsedDocumentRate = toRate(documentRate);
    const parsedUsdRate = toRate(usdRate);

    if (includeProfit && needsDocumentRate && !parsedDocumentRate) {
      setError(t('bis_exchange_rate_required'));
      return;
    }

    if (includeProfit && showUsdRate && parsedUsdRate) {
      storeUsdRate(parsedUsdRate);
    }

    setIsDownloading(true);

    download(excelRequest.resource, excelRequest.entity, {
      profit: includeProfit,
      currency,
      documentRate: showDocumentRate ? parsedDocumentRate : undefined,
      usdRate: showUsdRate ? parsedUsdRate : undefined,
    }).finally(close);
  };

  return (
    <Modal
      title={t('bis_excel_download')}
      visible={Boolean(excelRequest)}
      onClose={close}
      size="regular"
    >
      <div className="flex flex-col space-y-5">
        <Toggle
          label={t('bis_excel_profit_table')}
          checked={includeProfit}
          onValueChange={(value) => {
            setIncludeProfit(value);
            setError('');
          }}
        />

        <p className="text-sm text-gray-500">
          {t('bis_excel_profit_table_help')}
        </p>

        {includeProfit && (
          <>
            <div className="flex flex-col space-y-2">
              <span className="text-sm font-medium">
                {t('bis_excel_profit_currency')}
              </span>

              <Radio
                name="proforma_excel_currency"
                options={currencyOptions}
                defaultSelected={currency}
                onValueChange={(value) => {
                  setCurrency(value);
                  setError('');
                }}
              />
            </div>

            {showDocumentRate && (
              <InputField
                label={trans('bis_excel_rate', {
                  currency: documentCode,
                  company: companyLabel,
                })}
                value={documentRate}
                onValueChange={(value) => {
                  setDocumentRate(value);
                  setError('');
                }}
                changeOverride
              />
            )}

            {showUsdRate && (
              <InputField
                label={trans('bis_excel_rate', {
                  currency: 'USD',
                  company: companyLabel,
                })}
                value={usdRate}
                onValueChange={(value) => {
                  setUsdRate(value);
                  setError('');
                }}
                changeOverride
              />
            )}

            {(showDocumentRate || showUsdRate) && (
              <p className="text-sm text-gray-500">
                {t('bis_excel_rate_help')}
              </p>
            )}
          </>
        )}

        {error && <p className="text-sm text-red-600">{error}</p>}

        <div className="flex justify-end">
          <Button
            behavior="button"
            onClick={handleDownload}
            disabled={isDownloading}
            disableWithoutIcon
          >
            {t('download')}
          </Button>
        </div>
      </div>
    </Modal>
  );
}
