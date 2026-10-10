/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AxiosError, AxiosResponse } from 'axios';
import { useFormik } from 'formik';
import { useAtomValue } from 'jotai';
import { get } from 'lodash';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { MdRefresh } from 'react-icons/md';
import { useColorScheme } from '$app/common/colors';
import { endpoint, isHosted, isSelfHosted } from '$app/common/helpers';
import { request } from '$app/common/helpers/request';
import { toast } from '$app/common/helpers/toast/toast';
import { useCurrentAccount } from '$app/common/hooks/useCurrentAccount';
import { useCurrentCompany } from '$app/common/hooks/useCurrentCompany';
import { useRefreshCompanyUsers } from '$app/common/hooks/useRefreshCompanyUsers';
import { ValidationBag } from '$app/common/interfaces/validation-bag';
import { Card, Element } from '$app/components/cards';
import { Button, InputField } from '$app/components/forms';
import Toggle from '$app/components/forms/Toggle';
import { companySettingsErrorsAtom } from '../../common/atoms';
import { useHandleCurrentCompanyChangeProperty } from '../../common/hooks/useHandleCurrentCompanyChange';
import { Disconnect } from './Onboarding';

export function Preferences() {
  const { t } = useTranslation();
  const refresh = useRefreshCompanyUsers();
  const queryClient = useQueryClient();

  const colors = useColorScheme();
  const company = useCurrentCompany();
  const account = useCurrentAccount();

  const handleChange = useHandleCurrentCompanyChangeProperty();
  const errors = useAtomValue(companySettingsErrorsAtom);
  const { data: healthCheck } = useEInvoiceHealthCheck();
  const [isRegenerating, setIsRegenerating] = useState(false);

  const handleRegenerateToken = () => {
    setIsRegenerating(true);
    toast.processing();

    request('POST', endpoint('/api/v1/einvoice/token/update'))
      .then(() => {
        toast.success(t('token_regenerated')!);
        queryClient.invalidateQueries({
          queryKey: ['/api/v1/einvoice/health_check'],
        });
        queryClient.invalidateQueries({
          queryKey: ['/api/v1/einvoice/quota'],
        });
        refresh();
      })
      .catch(() => {
        toast.error(t('token_regeneration_failed')!);
      })
      .finally(() => {
        setIsRegenerating(false);
      });
  };

  const form = useFormik({
    initialValues: {
      acts_as_sender: company?.tax_data?.acts_as_sender,
      acts_as_receiver: company?.tax_data?.acts_as_receiver,
      legal_entity_id: company.legal_entity_id,
      e_invoicing_token: account?.e_invoicing_token,
    },
    onSubmit: (values) => {
      toast.processing();

      request('PUT', endpoint('/api/v1/einvoice/peppol/update'), values)
        .then(() => {
          toast.success(t('updated_settings')!);
        })
        .catch((error: AxiosError<ValidationBag>) => {
          if (error.response?.status === 422) {
            if (get(error.response.data, 'errors.acts_as_receiver.0')) {
              toast.error(
                get(error.response.data, 'errors.acts_as_receiver.0')
              );
            }

            return;
          }

          toast.error();
        })
        .finally(() => refresh());
    },
  });

  return (
    <>
      <Card
        title={`PEPPOL: ${t('preferences')}`}
        className="shadow-sm"
        style={{ borderColor: colors.$24 }}
        headerStyle={{ borderColor: colors.$20 }}
      >
        <Element leftSide={t('status')}>
          <div className="flex flex-col">
            <p>
              {t('connected')} ({company.legal_entity_id})
            </p>

            <div>
              <Disconnect />
            </div>

            {typeof healthCheck === 'boolean' && !healthCheck && (
              <div className="mt-2">
                <Button
                  behavior="button"
                  onClick={handleRegenerateToken}
                  disabled={isRegenerating}
                >
                  <span className="flex items-center gap-1">
                    <MdRefresh
                      className={isRegenerating ? 'animate-spin' : ''}
                    />
                    {t('regenerate_token')}
                  </span>
                </Button>
              </div>
            )}
          </div>
        </Element>

        {company.legal_entity_id && (
          <Element leftSide={t('act_as_sender')}>
            <Toggle
              checked={form.values.acts_as_sender}
              onValueChange={(v) => {
                form.setFieldValue('acts_as_sender', v);
                form.submitForm();
              }}
            />
          </Element>
        )}

        {company.legal_entity_id && (
          <Element leftSide={t('act_as_receiver')}>
            <Toggle
              checked={form.values.acts_as_receiver}
              onValueChange={(v) => {
                form.setFieldValue('acts_as_receiver', v);
                form.submitForm();
              }}
            />
          </Element>
        )}

        {company.legal_entity_id && (
          <Element
            leftSide={t('e_invoice_forward_email')}
            leftSideHelp={t('e_invoice_forward_email_help')}
          >
            <InputField
              value={company?.settings.e_invoice_forward_email || ''}
              onValueChange={(value) =>
                handleChange('settings.e_invoice_forward_email', value)
              }
              errorMessage={errors?.errors['settings.e_invoice_forward_email']}
            />
          </Element>
        )}

        {company.legal_entity_id && (
          <Element
            leftSide={t('e_expense_forward_email')}
            leftSideHelp={t('e_expense_forward_email_help')}
          >
            <InputField
              value={company?.settings.e_expense_forward_email || ''}
              onValueChange={(value) =>
                handleChange('settings.e_expense_forward_email', value)
              }
              errorMessage={errors?.errors['settings.e_expense_forward_email']}
            />
          </Element>
        )}

        {company.legal_entity_id && (
          <Element
            leftSide={t('skip_automatic_email_with_peppol')}
            leftSideHelp={t('skip_automatic_email_with_peppol_help')}
          >
            <Toggle
              checked={Boolean(
                company?.settings.skip_automatic_email_with_peppol
              )}
              onValueChange={(value) =>
                handleChange('settings.skip_automatic_email_with_peppol', value)
              }
            />
          </Element>
        )}

        {company.legal_entity_id && (
          <Element leftSide={t('credits')}>
            <div className="flex items-center gap-1">
              <p>{t('total_credits_amount')}:</p>
              <Quota />
            </div>
          </Element>
        )}
      </Card>
    </>
  );
}

export function useTriggerEInvoiceRoutes() {
  const company = useCurrentCompany();

  return (
    isSelfHosted() &&
    Boolean(company) &&
    company?.settings.e_invoice_type === 'PEPPOL'
  );
}

export function useQuota() {
  const account = useCurrentAccount();
  const queryClient = useQueryClient();

  const quota = useQuery({
    queryKey: ['/api/v1/einvoice/quota'],
    queryFn: () =>
      request('GET', endpoint('/api/v1/einvoice/quota'))
        .then((response: AxiosResponse<{ quota: string }>) => response.data)
        .catch((error: AxiosError<{ message: string }>) => {
          if (error.response?.status === 422) {
            toast.error(error.response.data.message);
          }
        }),
    enabled:
      useTriggerEInvoiceRoutes() &&
      queryClient.getQueryData(['/api/v1/einvoice/health_check']) !== undefined,
    retry: () => false,
    staleTime: Infinity,
  });

  const count = () => {
    if (isHosted()) {
      return parseInt(account?.e_invoice_quota);
    }

    if (quota) {
      return typeof quota.data?.quota === 'number'
        ? parseInt(quota.data.quota)
        : null;
    }

    return null;
  };

  return count();
}

function Quota() {
  const quota = useQuota();

  return (
    <div>
      <p>{quota}</p>
    </div>
  );
}

export function useEInvoiceHealthCheck() {
  return useQuery({
    queryKey: ['/api/v1/einvoice/health_check'],
    queryFn: () =>
      request('GET', endpoint('/api/v1/einvoice/health_check'))
        .then(() => true)
        .catch(() => false),
    enabled: useTriggerEInvoiceRoutes(),
    staleTime: Infinity,
    retry: () => false,
  });
}
