/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { useTranslation } from 'react-i18next';
import { MdWarning } from 'react-icons/md';
import { Tooltip } from '$app/components/Tooltip';

interface Props {
  size?: number;
}
export function UserUnsubscribedTooltip(props?: Props) {
  const [t] = useTranslation();

  const { size = 22 } = props || {};

  return (
    <Tooltip
      tooltipElement={<span>{t('bis_user_unsubscribed')}</span>}
      width="auto"
      placement="top"
    >
      <MdWarning color="red" size={size} />
    </Tooltip>
  );
}
