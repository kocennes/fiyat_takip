/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

// Fallbacks used while a company has not uploaded its own logo.
// `logo` sits on light surfaces, `logoWhite` on dark ones and `smallLogo`
// (square icon on white) is shown as a round avatar on either.
import SmallLogo from '/brand/bisavunma-icon-192.png?url';
import DefaultLogo from '/brand/bisavunma-logo.png?url';
import DefaultLogoWhite from '/brand/bisavunma-logo-white.png?url';

export default {
  logo: DefaultLogo,
  logoWhite: DefaultLogoWhite,
  smallLogo: SmallLogo,
};
