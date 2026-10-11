// Gateway help / website links (the statics `site_url`, seeded by the backend)
// that lead to pages about the upstream vendor's product (e.g. BTCPay's
// integration guide) or carry its partner / referral codes are not shown.
const UPSTREAM_LINK =
  /invoice-?ninja|invoicing\.co|[?&]r=2c37ac2298|reseller\.authorize\.net|sagepay\.com\/apply\//i;

export function gatewayWebsite(siteUrl: string | undefined): string {
  return siteUrl && !UPSTREAM_LINK.test(siteUrl) ? siteUrl : '';
}
