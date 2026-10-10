/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

interface Params {
  entity?: 'client';
}

// Upstream replaced the hosted-plan client limit error with an "upgrade your
// plan" prompt here. This installation has no plans, so validation messages
// are shown exactly as the server returns them.
export function useValidationMessageAlias(_params?: Params) {
  return (_property: string, message: string[]) => message;
}
