/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import CommonProps from '../common/interfaces/common-props.interface';

interface Props extends CommonProps {
  message?: string;
}

// Every feature is available in this deployment, so the plan/trial upsell
// alert never renders. The props are kept so existing call sites compile.
export function AdvancedSettingsPlanAlert(_props: Props) {
  return null;
}
