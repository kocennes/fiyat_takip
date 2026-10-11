/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { useColorScheme } from '$app/common/colors';
import { useCompanyChanges } from '$app/common/hooks/useCompanyChanges';
import { TaxRate } from '$app/common/interfaces/tax-rate';
import { useTaxRatesQuery } from '$app/common/queries/tax-rates';
import { Card, Element } from '$app/components/cards';
import { SelectField } from '$app/components/forms';
import { companySettingsErrorsAtom } from '../../common/atoms';
import { useHandleCurrentCompanyChangeProperty } from '../../common/hooks/useHandleCurrentCompanyChange';

/**
 * BISAVUNMA: Turkish KDV is charged per line item with a single tax line,
 * so only the first default line item tax (settings tax_name1 / tax_rate1,
 * e.g. KDV %20) is offered.
 */
export function DefaultLineItemTaxes() {
  const [t] = useTranslation();

  const colors = useColorScheme();
  const companyChanges = useCompanyChanges();
  const errors = useAtomValue(companySettingsErrorsAtom);

  const { data } = useTaxRatesQuery({ perPage: 100, status: ['active'] });

  const handlePropertyChange = useHandleCurrentCompanyChangeProperty();

  const handleChange = (nameValue: string, rateValue: number) => {
    if (!nameValue) {
      handlePropertyChange('settings.tax_name1', '');
      handlePropertyChange('settings.tax_rate1', 0);
    } else {
      handlePropertyChange('settings.tax_name1', nameValue);
      handlePropertyChange('settings.tax_rate1', rateValue);
    }
  };

  if (
    !Number(companyChanges.enabled_item_tax_rates) ||
    Number(companyChanges.enabled_tax_rates)
  ) {
    return null;
  }

  return (
    <Card className="shadow-sm" style={{ borderColor: colors.$24 }}>
      {data && (
        <Element
          leftSide={t('default_tax_rate')}
          leftSideHelp={t('bis_default_line_item_tax_help')}
        >
          <SelectField
            value={
              companyChanges?.settings?.tax_name1
                ? `${companyChanges?.settings?.tax_name1}|%|${companyChanges?.settings?.tax_rate1}`
                : ''
            }
            onValueChange={(value) =>
              handleChange(
                value.split('|%|')?.[0],
                Number(value.split('|%|')?.[1] || 0)
              )
            }
            withBlank
            errorMessage={errors?.errors['settings.tax_rate1']}
            customSelector
          >
            {data.data.data.map((taxRate: TaxRate) => (
              <option
                key={taxRate.id}
                value={`${taxRate.name}|%|${taxRate.rate}`}
              >
                {taxRate.name} %{taxRate.rate}
              </option>
            ))}
          </SelectField>
        </Element>
      )}
    </Card>
  );
}
