/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { useQuery } from '@tanstack/react-query';
import Tippy from '@tippyjs/react';
import classNames from 'classnames';
import dayjs from 'dayjs';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useDispatch } from 'react-redux';
import { useColorScheme } from '$app/common/colors';
import { endpoint, isSelfHosted } from '$app/common/helpers';
import { request } from '$app/common/helpers/request';
import { toast } from '$app/common/helpers/toast/toast';
import { useCurrentAccount } from '$app/common/hooks/useCurrentAccount';
import { useHandleCollapseExpandSidebar } from '$app/common/hooks/useHandleCollapseExpandSidebar';
import { useHandleDarkLightMode } from '$app/common/hooks/useHandleDarkLightMode';
import { useReactSettings } from '$app/common/hooks/useReactSettings';
import {
  resetChanges,
  updateCompanyUsers,
} from '$app/common/stores/slices/company-users';
import { AboutModal } from './AboutModal';
import { Button } from './forms';
import { CircleInfo } from './icons/CircleInfo';
import { CircleWarning } from './icons/CircleWarning';
import { CloseNavbarArrow } from './icons/CloseNavbarArrow';
import { Icon } from './icons/Icon';
import { MoonStars } from './icons/MoonStars';
import { OpenNavbarArrow } from './icons/OpenNavbarArrow';
import { Sun } from './icons/Sun';
import { Modal } from './Modal';

interface Props {
  docsLink?: string;
  mobileNavbar?: boolean;
}

export function HelpSidebarIcons(props: Props) {
  const [t] = useTranslation();

  const colors = useColorScheme();
  const account = useCurrentAccount();

  const reactSettings = useReactSettings();

  const { mobileNavbar } = props;

  const dispatch = useDispatch();
  const handleDarkLightMode = useHandleDarkLightMode();
  const handleCollapseExpandSidebar = useHandleCollapseExpandSidebar();

  const { data: currentSystemInfo } = useQuery({
    queryKey: ['/api/v1/health_check'],
    queryFn: () =>
      request('GET', endpoint('/api/v1/health_check')).then(
        (response) => response.data
      ),
    staleTime: Infinity,
    enabled: isSelfHosted(),
  });

  const [isAboutVisible, setIsAboutVisible] = useState<boolean>(false);
  const [cronsNotEnabledModal, setCronsNotEnabledModal] =
    useState<boolean>(false);
  const [disabledButton, setDisabledButton] = useState<boolean>(false);

  const isMiniSidebar = Boolean(reactSettings.show_mini_sidebar);

  const refreshData = () => {
    setDisabledButton(true);

    request(
      'POST',
      endpoint('/api/v1/refresh?updated_at=:updatedAt', {
        updatedAt: dayjs().unix(),
      })
    ).then((data) => {
      dispatch(updateCompanyUsers(data.data.data));
      dispatch(resetChanges('company'));
      setDisabledButton(false);
      setCronsNotEnabledModal(false);
    });
  };

  return (
    <>
      <Modal
        title={t('crons_not_enabled')}
        visible={cronsNotEnabledModal}
        onClose={setCronsNotEnabledModal}
      >
        <Button
          onClick={() => {
            window.open(
              'https://bisavunma.com',
              '_blank'
            );
          }}
        >
          {t('learn_more')}
        </Button>
        <Button disabled={disabledButton} onClick={refreshData}>
          {t('refresh_data')}
        </Button>
        <Button
          onClick={() => {
            setCronsNotEnabledModal(false);
          }}
        >
          {t('dismiss')}
        </Button>
      </Modal>

      <AboutModal
        isAboutVisible={isAboutVisible}
        setIsAboutVisible={setIsAboutVisible}
        currentSystemInfo={currentSystemInfo}
      />

      <nav
        style={{ borderColor: colors.$5 }}
        className={classNames('flex space-x-2.5 py-4 px-2 text-white border-t', {
          'justify-end': mobileNavbar,
          'justify-around': !mobileNavbar,
        })}
      >
        {!isMiniSidebar && !mobileNavbar && (
          <>
            {isSelfHosted() && account && !account.is_scheduler_running && (
              <Tippy
                duration={0}
                content={t('error')}
                className="rounded-md text-xs p-2 bg-[#F2F2F2]"
              >
                <div
                  className="cursor-pointer"
                  onClick={() => setCronsNotEnabledModal(true)}
                >
                  <CircleWarning color="white" size="1.3rem" />
                </div>
              </Tippy>
            )}

            <Tippy
              duration={0}
              content={t('about')}
              className="rounded-md text-xs p-2 bg-[#F2F2F2]"
            >
              <div
                className="cursor-pointer"
                onClick={() => setIsAboutVisible(true)}
              >
                <CircleInfo color="white" size="1.3rem" />
              </div>
            </Tippy>

            <Tippy
              duration={0}
              content={t('dark_mode')}
              className="rounded-md text-xs p-2 bg-[#F2F2F2]"
            >
              <div
                className="cursor-pointer"
                onClick={() => handleDarkLightMode(!reactSettings?.dark_mode)}
              >
                {reactSettings?.dark_mode ? (
                  <Sun color="white" size="1.3rem" />
                ) : (
                  <MoonStars color="white" size="1.3rem" />
                )}
              </div>
            </Tippy>
          </>
        )}

        <Tippy
          duration={0}
          content={
            <span style={{ fontSize: isMiniSidebar ? '0.6rem' : '0.75rem' }}>
              {isMiniSidebar ? t('show_menu') : t('hide_menu')}
            </span>
          }
          className="rounded-md text-xs p-2 bg-[#F2F2F2]"
        >
          <div
            className="cursor-pointer"
            onClick={() => handleCollapseExpandSidebar(!isMiniSidebar)}
          >
            {isMiniSidebar ? (
              <OpenNavbarArrow color="#e5e7eb" size="1.5rem" />
            ) : (
              <CloseNavbarArrow color="#e5e7eb" size="1.35rem" />
            )}
          </div>
        </Tippy>
      </nav>
    </>
  );
}
