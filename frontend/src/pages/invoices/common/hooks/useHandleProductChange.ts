/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { productLineAmounts } from '$app/common/helpers/product-currency';
import { toast } from '$app/common/helpers/toast/toast';
import { useCurrentCompany } from '$app/common/hooks/useCurrentCompany';
import { useResolveDocumentCurrency } from '$app/common/hooks/useResolveDocumentCurrency';
import { InvoiceItem } from '$app/common/interfaces/invoice-item';
import { Product } from '$app/common/interfaces/product';
import {
  ProductTableResource,
  RelationType,
} from '../components/ProductsTable';

interface Props {
  relationType: RelationType;
  resource: ProductTableResource;
  type: 'product' | 'task';
  onChange: (index: number, lineItem: InvoiceItem) => unknown;
}

export function useHandleProductChange(props: Props) {
  const company = useCurrentCompany();

  const resolveDocumentCurrency = useResolveDocumentCurrency();

  const resource = props.resource;

  return async (
    index: number,
    product_key: string,
    product: Product | null
  ) => {
    const lineItem = { ...resource.line_items[index] };

    lineItem.product_key = product?.product_key || product_key;

    if (!product) {
      // When we deal with inline product key
      // keep everything but the name.

      return props.onChange(index, lineItem);
    }

    // BISAVUNMA: product currency -> document currency with the product's manual rate
    // and the document's exchange rate (common/helpers/product-currency.ts).
    const amounts = productLineAmounts(
      product,
      await resolveDocumentCurrency(resource, props.relationType),
      company?.settings.currency_id,
      props.relationType === 'vendor_id'
    );

    if (amounts.missingDocumentRate) {
      toast.info('bis_document_exchange_rate_missing');
    }

    if (company.fill_products) {
      if (!company?.enable_product_quantity) {
        lineItem.quantity = 1;
      } else {
        lineItem.quantity = company?.default_quantity
          ? 1
          : (product?.quantity ?? 1);
      }

      lineItem.cost = amounts.cost;
    }

    if (!product) {
      lineItem.notes = '';
    }

    if (props.type == 'product' && product?.notes && company.fill_products) {
      lineItem.notes = product?.notes;
    }

    if (props.type == 'task' && product?.notes && !lineItem.notes) {
      lineItem.notes = product.notes;
    }

    lineItem.tax_name1 = product?.tax_name1 || '';
    lineItem.tax_name2 = product?.tax_name2 || '';
    lineItem.tax_name3 = product?.tax_name3 || '';

    lineItem.tax_rate1 = product?.tax_rate1 || 0;
    lineItem.tax_rate2 = product?.tax_rate2 || 0;
    lineItem.tax_rate3 = product?.tax_rate3 || 0;

    lineItem.custom_value1 = product?.custom_value1 || '';
    lineItem.custom_value2 = product?.custom_value2 || '';
    lineItem.custom_value3 = product?.custom_value3 || '';
    lineItem.custom_value4 = product?.custom_value4 || '';
    lineItem.tax_id = product?.tax_id || '1';

    lineItem.product_cost = amounts.product_cost;

    if (import.meta.env.VITE_DISABLE_QUICKBOOKS_INTEGRATION !== 'true') {
      lineItem.income_account_id = product?.income_account_id || '';
    }

    return props.onChange(index, lineItem);
  };
}
