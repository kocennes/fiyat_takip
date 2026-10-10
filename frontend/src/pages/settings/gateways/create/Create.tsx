/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { arrayMoveImmutable } from 'array-move';
import classNames from 'classnames';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useColorScheme } from '$app/common/colors';
import { isHosted } from '$app/common/helpers';
import { useTitle } from '$app/common/hooks/useTitle';
import { CompanyGateway } from '$app/common/interfaces/company-gateway';
import { Gateway } from '$app/common/interfaces/statics';
import { ValidationBag } from '$app/common/interfaces/validation-bag';
import {
  useBlankCompanyGatewayQuery,
  useCompanyGatewaysQuery,
} from '$app/common/queries/company-gateways';
import { Card, Element } from '$app/components/cards';
import { Button, Link, SelectField } from '$app/components/forms';
import { Settings } from '$app/components/layouts/Settings';
import { TabGroup } from '$app/components/TabGroup';
import {
  availableGatewayLogos,
  GatewayLogoName,
  GatewayTypeIcon,
} from '$app/pages/clients/show/components/GatewayTypeIcon';
import { useGateways } from '../common/hooks/useGateways';
import { Credentials } from './components/Credentials';
import { DuplicatingGatewayModal } from './components/DuplicatingGatewayModal';
import { LimitsAndFees } from './components/LimitsAndFees';
import { RequiredFields } from './components/RequiredFields';
import { Settings as GatewaySettings } from './components/Settings';
import { useHandleCreate } from './hooks/useHandleCreate';
import { blankFeesAndLimitsRecord } from './hooks/useHandleMethodToggle';

const gatewaysStyles = [
  { name: 'paypal_ppcp', width: 110 },
  { name: 'paypal_express', width: 110 },
  { name: 'mollie', width: 110 },
  { name: 'eway', width: 170 },
  { name: 'forte', width: 190 },
  { name: 'square', width: 130 },
  { name: 'checkoutcom', width: 170 },
  { name: 'btcpay', width: 90 },
  { name: 'blockonomics', width: 180 },
];

export const gatewaysDetails = [
  { name: 'stripe', key: 'd14dd26a37cecc30fdd65700bfb55b23' },
  { name: 'stripe', key: 'd14dd26a47cecc30fdd65700bfb67b34' },
  { name: 'paypal_platform', key: '80af24a6a691230bbec33e930ab40666' },
  { name: 'paypal_rest', key: '80af24a6a691230bbec33e930ab40665' },
  { name: 'braintree', key: 'f7ec488676d310683fb51802d076d713' },
  { name: 'paypal_ppcp', key: '80af24a6a691230bbec33e930ab40666' },
  { name: 'authorize', key: '3b6621f970ab18887c4f6dca78d3f8bb' },
  { name: 'mollie', key: '1bd651fb213ca0c9d66ae3c336dc77e8' },
  { name: 'gocardless', key: 'b9886f9257f0c6ee7c302f1c74475f6c' },
  { name: 'forte', key: 'kivcvjexxvdiyqtj3mju5d6yhpeht2xs' },
  { name: 'razorpay', key: 'hxd6gwg3ekb9tb3v9lptgx1mqyg69zu9' },
  { name: 'square', key: '65faab2ab6e3223dbe848b1686490baz' },
  { name: 'paytrace', key: 'bbd736b3254b0aabed6ad7fda1298c88' },
  { name: 'checkoutcom', key: '3758e7f7c6f4cecf0f4f348b9a00f456' },
  { name: 'payfast', key: 'd6814fc83f45d2935e7777071e629ef9' },
  { name: 'eway', key: '944c20175bbe6b9972c05bcfe294c2c7' },
  { name: 'btcpay', key: 'vpyfbmdrkqcicpkjqdusgjfluebftuva' },
  { name: 'blockonomics', key: 'wbhf02us6owgo7p4nfjd0ymssdshks4d' },
  { name: 'payware', key: 'b0a6294fca4488c2bab58f3e11e3c623' },
];

// Gateways that can only be onboarded through the upstream vendor's hosted
// platform (Stripe Connect, PayPal Platform, WePay); not offered here.
const upstreamPlatformGateways = [
  'd14dd26a47cecc30fdd65700bfb67b34',
  '80af24a6a691230bbec33e930ab40666',
  '8fdeed552015b3c7b44ed6c8ebd9e992',
];

const hostedGatewayFilter = [
  '38f2c48af60c7dd69e04248cbb24c36e', //do not allow express to be created in hosted
  '80af24a6a691230bbec33e930ab40665', //do not allow pp rest to be created in hosted
  '8fdeed552015b3c7b44ed6c8ebd9e992', //do not allow wepay to be created in hosted
];

export function Create() {
  const [t] = useTranslation();

  const { documentTitle } = useTitle('add_gateway');

  const pages = [
    { name: t('settings'), href: '/settings' },
    { name: t('online_payments'), href: '/settings/online_payments' },
    { name: t('add_gateway'), href: '/settings/gateways/create' },
  ];

  const defaultTab = [t('payment_provider')];

  const additionalTabs = [
    t('credentials'),
    t('settings'),
    t('required_fields'),
    t('limits_and_fees'),
  ];

  const gateways = useGateways();
  const colors = useColorScheme();

  const { data: blankCompanyGateway } = useBlankCompanyGatewayQuery();
  const { data: companyGatewaysResponse } = useCompanyGatewaysQuery({
    status: 'active',
    perPage: '1000',
  });

  const [gateway, setGateway] = useState<Gateway>();
  const [tabIndex, setTabIndex] = useState<number>(0);
  const [errors, setErrors] = useState<ValidationBag>();
  const [tabs, setTabs] = useState<string[]>(defaultTab);
  const [isFormBusy, setIsFormBusy] = useState<boolean>(false);
  const [createBySetup, setCreateBySetup] = useState<boolean>(false);
  const [companyGateway, setCompanyGateway] = useState<CompanyGateway>();
  const [filteredGateways, setFilteredGateways] = useState<Gateway[]>([]);
  const [isDuplicatingGatewayModalOpen, setIsDuplicatingGatewayModalOpen] =
    useState<boolean>(false);

  const onSave = useHandleCreate({
    companyGateway,
    setErrors,
    setIsFormBusy,
    isFormBusy,
  });

  const handleChange = (value: string, isManualChange?: boolean) => {
    const gateway = gateways.find((gateway) => gateway.id === value);

    const isDuplicating =
      gateway &&
      companyGatewaysResponse?.data.data.some(
        (companyGateway: CompanyGateway) =>
          companyGateway.gateway_key === gateway?.key
      );

    if (isDuplicating) {
      setIsDuplicatingGatewayModalOpen(true);
    }

    setGateway(gateway);

    if (isManualChange && value && !isDuplicating) {
      setTabIndex(1);
    }
  };

  const handleOnDuplicatingGatewayConfirm = () => {
    if (gateway && !createBySetup) {
      setTabIndex(1);
    }

    setIsDuplicatingGatewayModalOpen(false);
  };

  const handleOnDuplicatingGatewayCancel = () => {
    setGateway(undefined);
    setIsDuplicatingGatewayModalOpen(false);
    setTabIndex(0);
    createBySetup && setCreateBySetup(false);
  };

  const getGatewayNameByKey = (key: string) => {
    const gateway = gatewaysDetails.find((gateway) => gateway.key === key);

    return gateway?.name || '';
  };

  const getGatewayWidth = (gatewayName: string) => {
    const gateway = gatewaysStyles.find(
      (gateway) => gateway.name === gatewayName
    );

    return gateway ? gateway.width : undefined;
  };

  useEffect(() => {
    if (gateways) {
      const availableGateways = gateways.filter(
        (gateway) => !upstreamPlatformGateways.includes(gateway.key)
      );

      if (isHosted()) {
        const mutated_gateways = availableGateways.filter((gateway) => {
          return !hostedGatewayFilter.includes(gateway.key);
        });
        setFilteredGateways(mutated_gateways);
      } else {
        const payPalRestIndex = availableGateways.findIndex(
          ({ key }) => key === '80af24a6a691230bbec33e930ab40665'
        );

        if (payPalRestIndex >= 0) {
          const sortedGateways: Gateway[] = arrayMoveImmutable(
            availableGateways as Gateway[],
            payPalRestIndex,
            1
          );

          setFilteredGateways(sortedGateways);
        } else {
          setFilteredGateways(availableGateways);
        }
      }
    }
  }, [gateways]);

  useEffect(() => {
    if (blankCompanyGateway?.data.data && companyGateway === undefined) {
      setCompanyGateway(blankCompanyGateway.data.data);
    }
  }, [blankCompanyGateway, gateway]);

  useEffect(() => {
    setCompanyGateway(
      (companyGateway) =>
        companyGateway &&
        gateway && {
          ...companyGateway,
          gateway_key: gateway.key,
          // config: gateway.fields,
          token_billing: 'always',
        }
    );

    // We want to inject blank credit card record which is checked.
    // Only in case the gateway supports credit card transactions.

    const supportedGatewayTypes = gateway
      ? Object.entries(gateway.options)
      : [];

    const shouldInjectCreditCard = supportedGatewayTypes.find(
      ([id]) => id === '1'
    );

    if (shouldInjectCreditCard) {
      setCompanyGateway(
        (current) =>
          current && {
            ...current,
            fees_and_limits: {
              ...current.fees_and_limits,
              '1': blankFeesAndLimitsRecord,
            },
          }
      );
    }
  }, [gateway]);

  useEffect(() => {
    if (gateway) {
      setTabs([...defaultTab, ...additionalTabs]);
    } else {
      setTabs([...defaultTab]);
    }
  }, [gateway]);

  useEffect(() => {
    if (createBySetup && !isDuplicatingGatewayModalOpen) {
      onSave(1);
      setCreateBySetup(false);
    }
  }, [companyGateway, isDuplicatingGatewayModalOpen]);

  useEffect(() => {
    if (!filteredGateways.length) return;

    const gatewayIndex = filteredGateways.findIndex((gateway) =>
      gateway.name?.toLowerCase().includes('paypal')
    );

    if (gatewayIndex === -1 || gatewayIndex === 1) return;

    const updatedGateways = [...filteredGateways];
    const [gateway] = updatedGateways.splice(gatewayIndex, 1);
    updatedGateways.splice(1, 0, gateway);

    setFilteredGateways(updatedGateways);
  }, [filteredGateways]);

  return (
    <Settings
      title={documentTitle}
      breadcrumbs={pages}
      onSaveClick={() => onSave(1)}
      disableSaveButton={!gateway || isFormBusy}
    >
      <DuplicatingGatewayModal
        visible={isDuplicatingGatewayModalOpen}
        onConfirm={handleOnDuplicatingGatewayConfirm}
        onCancel={handleOnDuplicatingGatewayCancel}
      />

      <Card
        title={t('add_gateway')}
        className="shadow-sm"
        style={{ borderColor: colors.$24 }}
        withoutBodyPadding
        withoutHeaderBorder
      >
        <TabGroup
          tabs={tabs}
          defaultTabIndex={tabIndex}
          withHorizontalPadding
          fullRightPadding
          horizontalPaddingWidth="1.5rem"
          onTabChange={(index) => setTabIndex(index)}
        >
          <div>
            <Element leftSide={t('payment_provider')}>
              <SelectField
                value={gateway?.id || ''}
                onValueChange={(value) => handleChange(value, true)}
                errorMessage={errors?.errors.gateway_key}
                customSelector
                withBlank
              >
                {filteredGateways.map((gateway, index) => (
                  <option value={gateway.id} key={index}>
                    {gateway.name}
                  </option>
                ))}
              </SelectField>
            </Element>
          </div>

          <div>
            {gateway && companyGateway && (
              <Credentials
                gateway={gateway}
                companyGateway={companyGateway}
                setCompanyGateway={setCompanyGateway}
                errors={errors}
              />
            )}
          </div>

          <div>
            {gateway && companyGateway && (
              <GatewaySettings
                gateway={gateway}
                companyGateway={companyGateway}
                setCompanyGateway={setCompanyGateway}
                errors={errors}
              />
            )}
          </div>

          <div>
            {gateway && companyGateway && (
              <RequiredFields
                gateway={gateway}
                companyGateway={companyGateway}
                setCompanyGateway={setCompanyGateway}
              />
            )}
          </div>

          <div>
            {gateway && companyGateway && (
              <LimitsAndFees
                gateway={gateway}
                companyGateway={companyGateway}
                setCompanyGateway={setCompanyGateway}
                errors={errors}
              />
            )}
          </div>
        </TabGroup>
      </Card>

      {!tabIndex && (
        <div className="flex flex-wrap gap-4">
          {filteredGateways.map(
            (gateway, index) =>
              availableGatewayLogos.includes(
                getGatewayNameByKey(gateway.key)
              ) && (
                <Card
                  key={index}
                  className="w-52 shadow-sm"
                  style={{ borderColor: colors.$24 }}
                >
                  <div className="flex flex-col items-center justify-between h-52">
                    <div
                      className="flex justify-center items-center border-b w-full h-28"
                      style={{ borderColor: colors.$20 }}
                    >
                      <GatewayTypeIcon
                        name={
                          getGatewayNameByKey(gateway.key) as GatewayLogoName
                        }
                        style={{
                          width:
                            getGatewayWidth(getGatewayNameByKey(gateway.key)) ||
                            150,
                        }}
                      />
                    </div>

                    <div
                      className={classNames('flex flex-col pt-4 flex-1', {
                        'justify-between': gateway.site_url,
                        'justify-end': !gateway.site_url,
                      })}
                    >
                      {gateway.site_url && (
                        <Link external to={gateway.site_url}>
                          {t('website')}
                        </Link>
                      )}

                      <Button
                        behavior="button"
                        onClick={() => {
                          if (
                            gateway.key !== '80af24a6a691230bbec33e930ab40666'
                          ) {
                            setCreateBySetup(true);
                          }

                          handleChange(gateway.id);
                        }}
                      >
                        {t('setup')}
                      </Button>
                    </div>
                  </div>
                </Card>
              )
          )}
        </div>
      )}
    </Settings>
  );
}
