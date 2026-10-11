/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { MdLink } from 'react-icons/md';
import { useClickAway } from 'react-use';
import { useColorScheme } from '$app/common/colors';
import { enterprisePlan } from '$app/common/guards/guards/enterprise-plan';
import { endpoint, isSelfHosted } from '$app/common/helpers';
import { request } from '$app/common/helpers/request';
import { useAccentColor } from '$app/common/hooks/useAccentColor';
import { Button, Link } from '$app/components/forms';
import { Icon } from '$app/components/icons/Icon';
import { Modal } from '$app/components/Modal';
import goCardlessLogo from '/dap-logos/goCardless.png';

export function ConnectAccounts() {
  const [t] = useTranslation();
  const accentColor = useAccentColor();

  const colors = useColorScheme();

  const divRef = useRef<HTMLDivElement>(null);

  const [account, setAccount] = useState<'yodlee' | 'nordigen'>();
  const [isModalVisible, setIsModalVisible] = useState<boolean>(false);

  useClickAway(divRef, () => {
    setAccount(undefined);
  });

  const handleClose = () => {
    setIsModalVisible(false);
    setAccount(undefined);
  };

  // Yodlee onboarding only works through the upstream vendor's hosted
  // service, so only the Nordigen (GoCardless) connection is offered.
  const handleConnectNordigen = () => {
    request('POST', endpoint('/api/v1/one_time_token'), {
      context: 'nordigen',
      platform: 'react',
    }).then((tokenResponse) => {
      handleClose();

      window.open(
        endpoint('/nordigen/connect/:hash', {
          hash: tokenResponse?.data?.hash,
        })
      );
    });
  };

  const handleConnectAccount = () => {
    if (account === 'nordigen') {
      handleConnectNordigen();
    }
  };

  return (
    <>
      <Button
        type="secondary"
        onClick={() =>
          isSelfHosted() ? handleConnectNordigen() : setIsModalVisible(true)
        }
      >
        <span className="mr-2">{<Icon element={MdLink} size={20} />}</span>
        {t('connect_accounts')}
      </Button>

      <Modal
        title={t('connect_accounts')}
        visible={isModalVisible}
        onClose={handleClose}
      >
        <div ref={divRef} className="flex flex-col space-y-6">
          {enterprisePlan() && (
            <div
              data-cy="nordigenBox"
              className="flex flex-col items-center cursor-pointer border-4"
              style={{
                borderColor: account === 'nordigen' ? accentColor : colors.$24,
                height: '10.25rem',
              }}
              onClick={() => setAccount('nordigen')}
            >
              <div className="flex flex-1 items-center justify-center">
                <img src={goCardlessLogo} style={{ width: '15rem' }} />
              </div>

              <div
                className="flex items-center justify-center space-x-2 text-xs pb-3"
                onClick={(event) => event.stopPropagation()}
              >
                <p className="text-gray-500">{t('nordigen_regions')}.</p>

                <Link
                  className="text-xs"
                  to="https://gocardless.com/bank-account-data/coverage/"
                  external
                >
                  {t('learn_more')}.
                </Link>
              </div>
            </div>
          )}

          <Button
            behavior="button"
            onClick={handleConnectAccount}
            disableWithoutIcon
            disabled={!account}
          >
            {t('connect')}
          </Button>
        </div>
      </Modal>
    </>
  );
}
