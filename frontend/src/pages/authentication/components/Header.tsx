/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { BrandLockup } from '$app/components/brand/BrandLockup';
import { Link } from '../../../components/forms/Link';

export function Header() {
  return (
    <>
      <div className="flex justify-center py-8">
        <Link to="/">
          <BrandLockup />
        </Link>
      </div>
    </>
  );
}
