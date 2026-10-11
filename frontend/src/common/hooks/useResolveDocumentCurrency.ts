/**
 * BISAVUNMA Fiyat Takip: currency of a document, for the product -> line item conversion
 * (common/helpers/product-currency.ts) and the document exchange-rate field. Like the upstream
 * editors: the client's currency (invoices, quotes, credits, recurring invoices) or the vendor's
 * currency (purchase orders), falling back to the company currency.
 */

import { DocumentCurrency } from '$app/common/helpers/product-currency';
import { Client } from '$app/common/interfaces/client';
import { Vendor } from '$app/common/interfaces/vendor';
import { RelationType } from '$app/pages/invoices/common/components/ProductsTable';
import { useClientResolver } from './clients/useClientResolver';
import { useCurrentCompany } from './useCurrentCompany';
import { useReactSettings } from './useReactSettings';
import { useResolveCurrency } from './useResolveCurrency';
import { useVendorResolver } from './vendors/useVendorResolver';

export interface DocumentCurrencySource {
  client_id?: string;
  vendor_id?: string;
  exchange_rate?: number;
  client?: Client;
  vendor?: Vendor;
}

export function useResolveDocumentCurrency() {
  const company = useCurrentCompany();
  const reactSettings = useReactSettings();

  const resolveCurrency = useResolveCurrency();
  const clientResolver = useClientResolver();
  const vendorResolver = useVendorResolver();

  return async (
    resource: DocumentCurrencySource | undefined,
    relationType: RelationType
  ): Promise<DocumentCurrency> => {
    let currencyId: string | undefined;

    if (relationType === 'vendor_id' && resource?.vendor_id) {
      const vendor: Vendor | undefined =
        resource.vendor?.id === resource.vendor_id
          ? resource.vendor
          : await vendorResolver
              .find(resource.vendor_id)
              .catch(() => undefined);

      currencyId = vendor?.currency_id;
    }

    if (relationType === 'client_id' && resource?.client_id) {
      const client: Client | undefined =
        resource.client?.id === resource.client_id
          ? resource.client
          : await clientResolver
              .find(resource.client_id)
              .catch(() => undefined);

      currencyId = client?.settings?.currency_id;
    }

    const documentCurrencyId =
      currencyId || company?.settings.currency_id || '';

    // Same precision as the line item price input.
    const userPrecision = Number(reactSettings?.number_precision);
    const currencyPrecision = Number(
      resolveCurrency(documentCurrencyId)?.precision
    );

    return {
      currencyId: documentCurrencyId,
      exchangeRate: resource?.exchange_rate ?? 1,
      precision:
        userPrecision > 0 && userPrecision <= 100
          ? userPrecision
          : Number.isInteger(currencyPrecision) && currencyPrecision >= 0
            ? currencyPrecision
            : 2,
    };
  };
}
