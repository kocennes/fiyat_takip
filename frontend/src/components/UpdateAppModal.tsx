/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { Dispatch, SetStateAction } from 'react';

interface Props {
  isVisible: boolean;
  setIsVisible: Dispatch<SetStateAction<boolean>>;
  installedVersion: string | undefined;
  latestVersion: string | undefined;
}

// In-app self-update pulls releases from the upstream project. Updates of this
// deployment are rolled out by the system administrator, so the prompt never
// renders.
export function UpdateAppModal(_props: Props) {
  return null;
}
