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
import { RegionComponent } from './RegionComponent';

export function SGRegions() {
  const [t] = useTranslation();

  return (
    <RegionComponent
      regionCode="SG"
      regionName={t('bis_region_singapore')}
      showSalesAboveThreshold
    />
  );
}
