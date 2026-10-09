/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

interface Props {
  id: string;
  url: string;
}

export function HelpWidget(_: Props) {
  return null;
}

export interface HelpOptions {
  moveToHeading: string;
}

export function $help(_: string, __?: HelpOptions) {}
