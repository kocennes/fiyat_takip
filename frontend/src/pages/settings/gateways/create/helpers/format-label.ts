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

/**
 * Format the input field for the gateway.
 * Transfer from camel case to normal words & then uppercase.
 *
 * @param label string
 * @returns string
 */
export function formatLabel(label: string): string {
  return label
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .replace(/(^\w|\s\w)/g, (word) => word.toUpperCase());
}

/**
 * Translation keys for the gateway credential fields, so the labels are
 * shown in the user's language instead of the raw (English) field name.
 */
const GATEWAY_FIELD_LABELS: Record<string, string> = {
  accessKey: 'access_key',
  accessToken: 'bis_gateway_access_token',
  accountId: 'account_id',
  apiAccessId: 'bis_gateway_api_access_id',
  apiKey: 'api_key',
  apiLoginId: 'bis_gateway_api_login_id',
  apiSecret: 'api_secret',
  apiToken: 'api_token',
  appleDomainVerification: 'bis_gateway_apple_domain_verification',
  applicationId: 'bis_gateway_application_id',
  authOrganizationId: 'bis_gateway_auth_organization_id',
  borderColor: 'border_color',
  brandName: 'bis_gateway_brand_name',
  btcpayUrl: 'bis_gateway_btcpay_url',
  clientId: 'client_id',
  developerEndpoint: 'bis_gateway_developer_endpoint',
  developerMode: 'bis_gateway_developer_mode',
  endpoint: 'endpoint',
  headerImageUrl: 'bis_gateway_header_image_url',
  integratorId: 'bis_gateway_integrator_id',
  landingPage: 'landing_page',
  liveEndpoint: 'bis_gateway_live_endpoint',
  locationId: 'bis_gateway_location_id',
  logoImageUrl: 'bis_gateway_logo_image_url',
  merchantAccountId: 'bis_gateway_merchant_account_id',
  merchantId: 'bis_gateway_merchant_id',
  merchantKey: 'bis_gateway_merchant_key',
  name: 'name',
  organizationId: 'bis_gateway_organization_id',
  passphrase: 'bis_gateway_passphrase',
  password: 'password',
  paywarePublicKey: 'bis_gateway_payware_public_key',
  pdtKey: 'bis_gateway_pdt_key',
  privateKey: 'bis_gateway_private_key',
  processingChannelId: 'bis_gateway_processing_channel_id',
  profileId: 'bis_gateway_profile_id',
  publicApiKey: 'bis_gateway_public_api_key',
  publicKey: 'public_key',
  publishableKey: 'publishable_key',
  returnUrl: 'return_url',
  secret: 'secret',
  secretApiKey: 'bis_gateway_secret_api_key',
  secretKey: 'secret_key',
  secureKey: 'bis_gateway_secure_key',
  signature: 'bis_gateway_signature',
  signatureKey: 'bis_gateway_signature_key',
  solutionType: 'bis_gateway_solution_type',
  storeId: 'bis_gateway_store_id',
  testMode: 'test_mode',
  text: 'text',
  threeds: 'bis_gateway_3d_secure',
  token: 'token',
  transactionKey: 'bis_gateway_transaction_key',
  username: 'username',
  verifyBankAccount: 'bis_gateway_verify_bank_account',
  webhookSecret: 'bis_gateway_webhook_secret',
  webhookVerifierToken: 'bis_gateway_webhook_verifier_token',
};

export function useFormatGatewayFieldLabel() {
  const [t] = useTranslation();

  return (field: string): string =>
    GATEWAY_FIELD_LABELS[field]
      ? t(GATEWAY_FIELD_LABELS[field])
      : formatLabel(field);
}
