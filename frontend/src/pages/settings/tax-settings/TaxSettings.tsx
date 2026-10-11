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
import { useDispatch } from 'react-redux';
import { useColorScheme } from '$app/common/colors';
import { useCompanyChanges } from '$app/common/hooks/useCompanyChanges';
import { useCurrentSettingsLevel } from '$app/common/hooks/useCurrentSettingsLevel';
import { useInjectCompanyChanges } from '$app/common/hooks/useInjectCompanyChanges';
import { useTitle } from '$app/common/hooks/useTitle';
import { updateChanges } from '$app/common/stores/slices/company-users';
import Toggle from '$app/components/forms/Toggle';
import { Card, Element } from '../../../components/cards';
import { SelectField } from '../../../components/forms';
import { Settings } from '../../../components/layouts/Settings';
import { TaxRates } from '..';
import { companySettingsErrorsAtom } from '../common/atoms';
import { useDiscardChanges } from '../common/hooks/useDiscardChanges';
import {
  isCompanySettingsFormBusy,
  useHandleCompanySave,
} from '../common/hooks/useHandleCompanySave';
import { useHandleCurrentCompanyChangeProperty } from '../common/hooks/useHandleCurrentCompanyChange';
import { DefaultLineItemTaxes } from './components/DefaultLineItemTaxes';

export function TaxSettings() {
  const [t] = useTranslation();

  const pages = [
    { name: t('settings'), href: '/settings' },
    { name: t('tax_settings'), href: '/settings/tax_settings' },
  ];

  useInjectCompanyChanges();
  useTitle('tax_settings');

  const colors = useColorScheme();

  const { isCompanySettingsActive } = useCurrentSettingsLevel();

  const errors = useAtomValue(companySettingsErrorsAtom);

  const dispatch = useDispatch();
  const companyChanges = useCompanyChanges();

  const handleChange = useHandleCurrentCompanyChangeProperty();

  const handleToggleChange = (id: string, value: boolean) => {
    dispatch(
      updateChanges({
        object: 'company',
        property: id,
        value,
      })
    );
  };

  const onSave = useHandleCompanySave();
  const onCancel = useDiscardChanges();
  const isFormBusy = useAtomValue(isCompanySettingsFormBusy);

  return (
    <Settings
      onSaveClick={onSave}
      onCancelClick={onCancel}
      title={t('tax_settings')}
      breadcrumbs={pages}
      disableSaveButton={isFormBusy}
    >
      {isCompanySettingsActive && (
        <>
          <Card
            title={t('tax_settings')}
            className="shadow-sm pb-6"
            childrenClassName="pt-4"
            style={{ borderColor: colors.$24 }}
            headerStyle={{ borderColor: colors.$20 }}
            withoutBodyPadding
          >
            {/* BISAVUNMA: Turkish KDV is charged per line item with a single
                tax line. There are no document-level taxes, no second or
                third tax lines and no automatic (US/EU) tax engine. */}
            <Element leftSide={t('line_item_tax_rates')}>
              <SelectField
                value={
                  companyChanges?.enabled_item_tax_rates?.toString() || '0'
                }
                onValueChange={(value) =>
                  handleChange('enabled_item_tax_rates', Number(value))
                }
                errorMessage={errors?.errors.enabled_item_tax_rates}
                customSelector
                dismissable={false}
              >
                <option value="0">{t('disabled')}</option>
                <option value="1">{t('one_tax_rate')}</option>
              </SelectField>
            </Element>

            <Element leftSide={t('expense_tax_rates')}>
              <SelectField
                value={
                  companyChanges?.enabled_expense_tax_rates?.toString() || '0'
                }
                onValueChange={(value) =>
                  handleChange('enabled_expense_tax_rates', Number(value))
                }
                errorMessage={errors?.errors.enabled_expense_tax_rates}
                customSelector
                dismissable={false}
              >
                <option value="0">{t('disabled')}</option>
                <option value="1">{t('one_tax_rate')}</option>
              </SelectField>
            </Element>

            <Element leftSide={t('inclusive_taxes')}>
              <div className="flex items-center space-x-7">
                <Toggle
                  onChange={(value: boolean) =>
                    handleToggleChange('settings.inclusive_taxes', value)
                  }
                  checked={Boolean(companyChanges?.settings.inclusive_taxes)}
                  cypressRef="inclusiveTaxToggle"
                />

                {companyChanges?.settings.inclusive_taxes ? (
                  <span>{t('inclusive')}: 100 + %20 = 83,33 + 16,67</span>
                ) : (
                  <span>{t('exclusive')}: 100 + %20 = 100 + 20</span>
                )}
              </div>
            </Element>
          </Card>

          {companyChanges.enabled_item_tax_rates > 0 &&
            !companyChanges.enabled_tax_rates && <DefaultLineItemTaxes />}
        </>
      )}

      <TaxRates />
    </Settings>
  );
}
